<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionAdminLog;
use App\Models\QuestionQualityReview;
use App\Services\DuelBotSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuestionQualityReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!DuelBotSettings::canManage($request->user())) {
                abort(403, 'Bu sayfaya erişim yetkiniz yok.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $band = trim((string) $request->query('band', ''));
        $q = trim((string) $request->query('q', ''));

        $query = QuestionQualityReview::query()
            ->with(['question' => function ($rel) {
                $rel->select('id', 'question', 'category_id', 'correct_answer', 'question_level', 'is_active', 'admin_status');
            }])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($band !== '') {
            $query->where('quality_band', 'like', '%' . $band . '%');
        }
        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where(function ($w) use ($q) {
                    $w->where('id', (int) $q)->orWhere('question_id', (int) $q);
                });
            } else {
                $query->where(function ($w) use ($q) {
                    $w->where('model', 'like', '%' . $q . '%')
                        ->orWhere('edit_reason', 'like', '%' . $q . '%')
                        ->orWhere('recommended_action', 'like', '%' . $q . '%');
                });
            }
        }

        $reviews = $query->paginate(30)->withQueryString();

        $stats = [
            'total' => QuestionQualityReview::query()->count(),
            'reviewed' => QuestionQualityReview::query()->where('status', 'reviewed')->count(),
            'pending' => QuestionQualityReview::query()->where('status', 'pending')->count(),
            'failed' => QuestionQualityReview::query()->where('status', 'failed')->count(),
            'questions_reviewed' => (int) QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->selectRaw('COUNT(DISTINCT question_id) as aggregate')
                ->value('aggregate'),
            'avg_score' => (float) (QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->whereNotNull('quality_score')
                ->avg('quality_score') ?? 0),
        ];

        $configuredModel = (string) config('services.anthropic.model_label', 'claude-opus-5');

        return view('admin.question-quality-reviews.index', compact(
            'reviews',
            'stats',
            'status',
            'band',
            'q',
            'configuredModel'
        ));
    }

    /** Liste auto-refresh için hafif durum snapshot. */
    public function poll()
    {
        $latest = QuestionQualityReview::query()
            ->orderByDesc('id')
            ->first(['id', 'status', 'reviewed_at', 'updated_at']);

        return response()->json([
            'pending' => QuestionQualityReview::query()->where('status', 'pending')->count(),
            'reviewed' => QuestionQualityReview::query()->where('status', 'reviewed')->count(),
            'failed' => QuestionQualityReview::query()->where('status', 'failed')->count(),
            'total' => QuestionQualityReview::query()->count(),
            'latest_id' => (int) ($latest?->id ?? 0),
            'latest_status' => (string) ($latest?->status ?? ''),
            'latest_updated' => optional($latest?->updated_at)->timestamp ?? 0,
        ]);
    }

    public function show(int $id)
    {
        $review = QuestionQualityReview::query()
            ->with('question.category')
            ->findOrFail($id);

        $configuredModel = (string) config('services.anthropic.model_label', 'claude-opus-5');

        return view('admin.question-quality-reviews.show', compact('review', 'configuredModel'));
    }

    /** Soruyu pasife al (is_active=0, admin_status=passive). */
    public function deactivateQuestion(int $id)
    {
        $review = QuestionQualityReview::query()->with('question')->findOrFail($id);
        $question = $review->question;
        if (!$question) {
            return back()->with('error', 'İlişkili soru bulunamadı.');
        }

        DB::transaction(function () use ($question) {
            $oldActive = $question->is_active ? '1' : '0';
            $oldStatus = (string) ($question->admin_status ?? '');

            $question->is_active = false;
            if (in_array($question->admin_status, ['active', 'passive', 'maintenance', null], true)
                || $question->admin_status === null
                || $question->admin_status === '') {
                $question->admin_status = 'passive';
            }
            $question->save();

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'ai_review_deactivate',
                'field' => 'is_active',
                'old_value' => $oldActive,
                'new_value' => '0',
            ]);

            if ($oldStatus !== (string) $question->admin_status) {
                QuestionAdminLog::create([
                    'question_id' => $question->id,
                    'admin_id' => Auth::id(),
                    'action' => 'ai_review_deactivate',
                    'field' => 'admin_status',
                    'old_value' => $oldStatus,
                    'new_value' => (string) $question->admin_status,
                ]);
            }
        });

        return back()->with('success', "Soru #{$question->id} pasife alındı.");
    }

    /** Soruyu tekrar aktif et. */
    public function activateQuestion(int $id)
    {
        $review = QuestionQualityReview::query()->with('question')->findOrFail($id);
        $question = $review->question;
        if (!$question) {
            return back()->with('error', 'İlişkili soru bulunamadı.');
        }

        DB::transaction(function () use ($question) {
            $oldActive = $question->is_active ? '1' : '0';
            $oldStatus = (string) ($question->admin_status ?? '');

            $question->is_active = true;
            $question->admin_status = 'active';
            $question->save();

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'ai_review_activate',
                'field' => 'is_active',
                'old_value' => $oldActive,
                'new_value' => '1',
            ]);

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'ai_review_activate',
                'field' => 'admin_status',
                'old_value' => $oldStatus,
                'new_value' => 'active',
            ]);
        });

        return back()->with('success', "Soru #{$question->id} aktif edildi.");
    }

    /**
     * AI'nin düzeltilmiş TR/EN içeriğini questions tablosuna uygula.
     */
    public function applyRevision(int $id)
    {
        $review = QuestionQualityReview::query()->with('question')->findOrFail($id);
        $question = $review->question;
        if (!$question) {
            return back()->with('error', 'İlişkili soru bulunamadı.');
        }

        $revised = $this->resolveRevisedContent($review);
        if ($revised === null) {
            return back()->with('error', 'Bu review’da uygulanabilir düzeltilmiş içerik yok.');
        }

        $tr = $revised['turkce'] ?? null;
        $en = $revised['ingilizce'] ?? null;
        if (!is_array($tr) || !is_array($en)) {
            return back()->with('error', 'Düzeltilmiş içerik formatı geçersiz (turkce/ingilizce).');
        }

        $trOpts = array_values($tr['secenekler'] ?? []);
        $enOpts = array_values($en['secenekler'] ?? []);
        if (count($trOpts) < 4 || count($enOpts) < 4) {
            return back()->with('error', 'Düzeltilmiş içerikte 4 şık olmalı.');
        }

        $trIdx = (int) ($tr['dogru_cevap_indeksi'] ?? -1);
        $enIdx = (int) ($en['dogru_cevap_indeksi'] ?? $trIdx);
        if ($trIdx < 0 || $trIdx > 3) {
            return back()->with('error', 'dogru_cevap_indeksi 0–3 olmalı.');
        }

        // TR ve EN indeks farklıysa TR’yi esas al
        $correctAnswer = (string) ($trIdx + 1);

        DB::transaction(function () use ($question, $tr, $en, $trOpts, $enOpts, $correctAnswer) {
            $oldCorrect = (string) $question->correct_answer;
            $oldTr = (string) ($question->getTranslation('question', 'tr', false) ?: '');

            $question->setTranslation('question', 'tr', trim((string) ($tr['soru'] ?? '')));
            $question->setTranslation('question', 'en', trim((string) ($en['soru'] ?? '')));

            $map = ['one_choice', 'two_choice', 'three_choice', 'four_choice'];
            foreach ($map as $i => $field) {
                $question->setTranslation($field, 'tr', trim((string) ($trOpts[$i] ?? '')));
                $question->setTranslation($field, 'en', trim((string) ($enOpts[$i] ?? '')));
            }

            $question->correct_answer = $correctAnswer;
            $question->check = true;
            $question->save();

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'ai_review_apply_revision',
                'field' => 'question_content',
                'old_value' => mb_substr($oldTr, 0, 500),
                'new_value' => mb_substr(trim((string) ($tr['soru'] ?? '')), 0, 500)
                    . ' | correct=' . $correctAnswer
                    . ( $oldCorrect !== $correctAnswer ? " (eski correct={$oldCorrect})" : ''),
            ]);
        });

        return back()->with('success', "Soru #{$question->id} AI düzeltmesi ile güncellendi.");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRevisedContent(QuestionQualityReview $review): ?array
    {
        if (is_array($review->revised_content) && $review->revised_content !== []) {
            return $review->revised_content;
        }

        $raw = is_array($review->raw_response) ? $review->raw_response : [];
        $analiz = is_array($raw['analiz_sonucu'] ?? null) ? $raw['analiz_sonucu'] : [];
        $rev = $analiz['duzeltilmis_icerik'] ?? null;

        return is_array($rev) ? $rev : null;
    }
}

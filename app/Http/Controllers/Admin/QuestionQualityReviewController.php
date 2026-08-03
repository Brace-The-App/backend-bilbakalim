<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $maxScore = $request->query('max_score');
        $maxScore = ($maxScore !== null && $maxScore !== '') ? (int) $maxScore : null;
        $perPage = (int) $request->query('per_page', 50);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }

        $query = QuestionQualityReview::query()
            ->with([
                'question' => function ($rel) {
                    $rel->select('id', 'question', 'category_id', 'correct_answer', 'question_level', 'is_active', 'admin_status');
                },
                'previousReview:id,status,attempt,edit_reason,reviewed_at',
            ])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($band !== '') {
            $query->where('quality_band', 'like', '%' . $band . '%');
        }
        if ($maxScore !== null) {
            $query->where('status', QuestionQualityReview::STATUS_REVIEWED)
                ->whereNotNull('quality_score')
                ->where('quality_score', '<=', max(0, min(100, $maxScore)));
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

        $reviews = $query->paginate($perPage)->withQueryString();

        $laterSuccessByQuestion = [];
        $failedIdsOnPage = $reviews->getCollection()
            ->where('status', 'failed')
            ->pluck('question_id', 'id');
        if ($failedIdsOnPage->isNotEmpty()) {
            $successors = QuestionQualityReview::query()
                ->whereIn('question_id', $failedIdsOnPage->values()->unique()->all())
                ->where('status', QuestionQualityReview::STATUS_REVIEWED)
                ->orderBy('id')
                ->get(['id', 'question_id', 'attempt', 'quality_score']);
            foreach ($failedIdsOnPage as $failId => $qid) {
                $hit = $successors->first(fn ($r) => (int) $r->question_id === (int) $qid && (int) $r->id > (int) $failId);
                if ($hit) {
                    $laterSuccessByQuestion[(int) $failId] = $hit;
                }
            }
        }

        $stats = [
            'total' => QuestionQualityReview::query()->count(),
            'reviewed' => QuestionQualityReview::query()->where('status', 'reviewed')->count(),
            'pending' => QuestionQualityReview::query()->where('status', 'pending')->count(),
            'failed' => QuestionQualityReview::query()->where('status', 'failed')->count(),
            'expired' => QuestionQualityReview::query()->where('status', 'expired')->count(),
            'questions_reviewed' => (int) QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->selectRaw('COUNT(DISTINCT question_id) as aggregate')
                ->value('aggregate'),
            'avg_score' => (float) (QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->whereNotNull('quality_score')
                ->avg('quality_score') ?? 0),
            'low_score' => QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->whereNotNull('quality_score')
                ->where('quality_score', '<=', 60)
                ->count(),
        ];

        $configuredModel = (string) config('services.anthropic.model_label', 'claude-opus-5');

        return view('admin.question-quality-reviews.index', compact(
            'reviews',
            'stats',
            'status',
            'band',
            'q',
            'maxScore',
            'perPage',
            'configuredModel',
            'laterSuccessByQuestion'
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
        $result = $this->applyRevisionToQuestion($review, 'live');

        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Çoklu seçim: AI düzeltmesini uygula.
     * mode=dry_run → canlıya yazmaz (önizleme)
     * mode=inactive_only → sadece is_active=0 sorular
     * mode=live → aktif sorular dahil (onay gerekir)
     */
    public function bulkApplyRevision(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer|distinct',
            'mode' => 'required|in:dry_run,inactive_only,live',
            'confirm_live' => 'nullable|boolean',
        ]);

        $mode = $validated['mode'];
        if ($mode === 'live' && empty($validated['confirm_live'])) {
            return response()->json([
                'success' => false,
                'message' => 'Canlı uygulama için confirm_live=1 gerekli.',
            ], 422);
        }

        $reviews = QuestionQualityReview::query()
            ->with('question')
            ->whereIn('id', $validated['ids'])
            ->where('status', QuestionQualityReview::STATUS_REVIEWED)
            ->get();

        $results = [];
        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($reviews as $review) {
            $row = $this->applyRevisionToQuestion($review, $mode);
            $results[] = [
                'review_id' => $review->id,
                'question_id' => $review->question_id,
                'score' => $review->quality_score,
                'ok' => $row['ok'],
                'skipped' => $row['skipped'] ?? false,
                'message' => $row['message'],
                'preview' => $row['preview'] ?? null,
            ];
            if (!empty($row['skipped'])) {
                $skipped++;
            } elseif ($row['ok']) {
                $applied++;
            } else {
                $failed++;
            }
        }

        $missing = array_values(array_diff($validated['ids'], $reviews->pluck('id')->all()));

        return response()->json([
            'success' => true,
            'mode' => $mode,
            'applied' => $applied,
            'skipped' => $skipped,
            'failed' => $failed,
            'missing_ids' => $missing,
            'results' => $results,
            'message' => $mode === 'dry_run'
                ? "Önizleme: {$applied} uygun, {$skipped} atlandı, {$failed} hata."
                : "Uygulandı: {$applied} · atlandı: {$skipped} · hata: {$failed}",
        ]);
    }

    /**
     * @return array{ok:bool,skipped?:bool,message:string,preview?:array<string,mixed>|null}
     */
    private function applyRevisionToQuestion(QuestionQualityReview $review, string $mode): array
    {
        $question = $review->question;
        if (!$question) {
            return ['ok' => false, 'message' => 'İlişkili soru yok.'];
        }

        $revised = $this->resolveRevisedContent($review);
        if ($revised === null) {
            return ['ok' => false, 'message' => 'Düzeltilmiş içerik yok.'];
        }

        $tr = $revised['turkce'] ?? null;
        $en = $revised['ingilizce'] ?? null;
        if (!is_array($tr) || !is_array($en)) {
            return ['ok' => false, 'message' => 'TR/EN blokları eksik.'];
        }

        $trOpts = array_values($tr['secenekler'] ?? []);
        $enOpts = array_values($en['secenekler'] ?? []);
        if (count($trOpts) < 4 || count($enOpts) < 4) {
            return ['ok' => false, 'message' => '4 şık gerekli.'];
        }

        $trIdx = (int) ($tr['dogru_cevap_indeksi'] ?? -1);
        if ($trIdx < 0 || $trIdx > 3) {
            return ['ok' => false, 'message' => 'dogru_cevap_indeksi 0–3 olmalı.'];
        }

        $correctAnswer = (string) ($trIdx + 1);
        $newTr = trim((string) ($tr['soru'] ?? ''));
        $oldTr = (string) ($question->getTranslation('question', 'tr', false) ?: '');
        $isActive = (bool) $question->is_active;

        $preview = [
            'question_id' => $question->id,
            'is_active' => $isActive,
            'old_question_tr' => mb_substr($oldTr, 0, 200),
            'new_question_tr' => mb_substr($newTr, 0, 200),
            'old_correct' => (string) $question->correct_answer,
            'new_correct' => $correctAnswer,
            'new_options_tr' => array_map(
                static fn ($o) => mb_substr(trim((string) $o), 0, 80),
                array_slice($trOpts, 0, 4)
            ),
        ];

        if ($mode === 'dry_run') {
            return [
                'ok' => true,
                'message' => 'Önizleme (yazılmadı)' . ($isActive ? ' · SORU CANLI' : ' · pasif soru'),
                'preview' => $preview,
            ];
        }

        if ($mode === 'inactive_only' && $isActive) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Canlı soru atlandı (inactive_only).',
                'preview' => $preview,
            ];
        }

        DB::transaction(function () use ($question, $en, $trOpts, $enOpts, $correctAnswer, $oldTr, $newTr) {
            $oldCorrect = (string) $question->correct_answer;

            $question->setTranslation('question', 'tr', $newTr);
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
                'new_value' => mb_substr($newTr, 0, 500)
                    . ' | correct=' . $correctAnswer
                    . ($oldCorrect !== $correctAnswer ? " (eski correct={$oldCorrect})" : ''),
            ]);
        });

        return [
            'ok' => true,
            'message' => "Soru #{$question->id} güncellendi.",
            'preview' => $preview,
        ];
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

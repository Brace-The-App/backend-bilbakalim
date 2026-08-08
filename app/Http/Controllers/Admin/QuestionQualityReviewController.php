<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionAdminLog;
use App\Models\QuestionQualityReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuestionQualityReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view question quality');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit question quality')
            ->only([
                'bulkApplyRevision',
                'deactivateQuestion',
                'activateQuestion',
                'applyRevision',
            ]);
    }

    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $band = trim((string) $request->query('band', ''));
        $q = trim((string) $request->query('q', ''));
        $maxScore = $request->query('max_score');
        $maxScore = ($maxScore !== null && $maxScore !== '') ? (int) $maxScore : null;
        $adminAccepted = in_array((string) $request->query('admin_accepted', ''), ['1', 'true'], true);
        $scope = trim((string) $request->query('scope', ''));
        $perPage = (int) $request->query('per_page', 50);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }

        $acceptedReviewIdsQuery = function ($sub) {
            $sub->select('ai_quality_review_id')
                ->from('questions')
                ->where('ai_accepted', 1)
                ->whereNotNull('ai_quality_review_id');
        };

        $query = QuestionQualityReview::query()
            ->with([
                'question' => function ($rel) {
                    $rel->select(
                        'id',
                        'question',
                        'category_id',
                        'correct_answer',
                        'question_level',
                        'is_active',
                        'admin_status',
                        'ai_accepted',
                        'ai_quality_review_id',
                        'check'
                    );
                },
                'previousReview:id,status,attempt,edit_reason,reviewed_at',
            ])
            // Her zaman en yeni kontrolden en eskiye
            ->orderByRaw('COALESCE(reviewed_at, assigned_at, created_at) DESC')
            ->orderByDesc('id');

        if ($adminAccepted) {
            $query->whereIn('id', $acceptedReviewIdsQuery);
        } elseif ($scope === 'all_success') {
            // Başarılı kontrol kartı: tüm reviewed (uygulananlar dahil), fail yok
            $query->where('status', QuestionQualityReview::STATUS_REVIEWED);
        } else {
            // Ana iş listelerinden admin'in kabul ettiklerini düş
            if ($status === '' || $status === 'reviewed' || $maxScore !== null) {
                $query->whereNotIn('id', $acceptedReviewIdsQuery);
            }
        }

        // Sonraki denemesi başarılı olan fail'leri gizle (sadece gerçekten açık fail kalsın)
        $openFailConstraint = function ($q) {
            $q->whereNotExists(function ($e) {
                $e->selectRaw('1')
                    ->from('question_quality_reviews as later_ok')
                    ->whereColumn('later_ok.question_id', 'question_quality_reviews.question_id')
                    ->where('later_ok.status', QuestionQualityReview::STATUS_REVIEWED)
                    ->whereColumn('later_ok.id', '>', 'question_quality_reviews.id');
            });
        };

        if ($status !== '' && $scope !== 'all_success') {
            $query->where('status', $status);
            // "Uygulama bekliyor" listesi: düzeltmesi olan başarılılar
            if ($status === 'reviewed' && !$adminAccepted) {
                $query->whereNotNull('revised_content')
                    ->whereRaw("revised_content NOT IN ('[]','{}','null')");
            }
            if ($status === 'failed') {
                $openFailConstraint($query);
            }
        } elseif ($scope !== 'all_success' && !$adminAccepted && $status === '') {
            // Genel listede çözülmüş fail'leri gösterme — başarı varsa fail satırı yok
            $query->where(function ($w) use ($openFailConstraint) {
                $w->where('status', '!=', QuestionQualityReview::STATUS_FAILED)
                    ->orWhere(function ($f) use ($openFailConstraint) {
                        $f->where('status', QuestionQualityReview::STATUS_FAILED);
                        $openFailConstraint($f);
                    });
            });
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

        $openFailedQuery = QuestionQualityReview::query()
            ->where('status', 'failed')
            ->whereNotExists(function ($e) {
                $e->selectRaw('1')
                    ->from('question_quality_reviews as later_ok')
                    ->whereColumn('later_ok.question_id', 'question_quality_reviews.question_id')
                    ->where('later_ok.status', QuestionQualityReview::STATUS_REVIEWED)
                    ->whereColumn('later_ok.id', '>', 'question_quality_reviews.id');
            });

        $stats = [
            'total' => QuestionQualityReview::query()->count(),
            'reviewed' => QuestionQualityReview::query()->where('status', 'reviewed')->count(),
            'pending' => QuestionQualityReview::query()->where('status', 'pending')->count(),
            // Sadece sonraki denemesi başarılı olmayan açık fail'ler
            'failed' => (clone $openFailedQuery)->count(),
            'expired' => QuestionQualityReview::query()->where('status', 'expired')->count(),
            // Sadece başarılı (reviewed) incelemeye sahip farklı sorular — fail sayılmaz
            'questions_reviewed' => (int) QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->selectRaw('COUNT(DISTINCT question_id) as aggregate')
                ->value('aggregate'),
            // Hâlâ açık fail'i olan farklı sorular (sonraki başarı varsa sayılmaz)
            'questions_failed' => (int) (clone $openFailedQuery)
                ->selectRaw('COUNT(DISTINCT question_id) as aggregate')
                ->value('aggregate'),
            'admin_accepted' => (int) \App\Models\Question::query()
                ->where('ai_accepted', true)
                ->whereNotNull('ai_quality_review_id')
                ->count(),
            // Uygulanabilir: başarılı + AI düzeltme var + bu review henüz uygulanmamış
            // Fail / pending burada yok; checkbox ve toplu uygula da aynı kural
            'reviewed_open' => QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->whereNotNull('revised_content')
                ->whereRaw("revised_content NOT IN ('[]','{}','null')")
                ->whereNotIn('id', $acceptedReviewIdsQuery)
                ->count(),
            'avg_score' => (float) (QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->whereNotNull('quality_score')
                ->avg('quality_score') ?? 0),
            'low_score' => QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->whereNotNull('quality_score')
                ->where('quality_score', '<=', 60)
                ->whereNotNull('revised_content')
                ->whereRaw("revised_content NOT IN ('[]','{}','null')")
                ->whereNotIn('id', $acceptedReviewIdsQuery)
                ->count(),
            // Günlük başarılı kontrol adetleri (TR günü)
            'reviewed_by_day' => QuestionQualityReview::query()
                ->where('status', 'reviewed')
                ->selectRaw("DATE(CONVERT_TZ(COALESCE(reviewed_at, created_at), '+00:00', '+03:00')) as d")
                ->selectRaw('COUNT(*) as c')
                ->groupBy('d')
                ->orderBy('d')
                ->get()
                ->mapWithKeys(fn ($r) => [(string) $r->d => (int) $r->c])
                ->all(),
            'daily_limit' => max(1, (int) config('ai_question_review.daily_limit', 250)),
        ];

        // Listedeki soruların toplam deneme sayısı (bilgi)
        $attemptCountByQuestion = [];
        $pageQids = $reviews->getCollection()->pluck('question_id')->unique()->filter()->values()->all();
        if ($pageQids !== []) {
            $attemptCountByQuestion = QuestionQualityReview::query()
                ->whereIn('question_id', $pageQids)
                ->selectRaw('question_id, COUNT(*) as cnt')
                ->groupBy('question_id')
                ->pluck('cnt', 'question_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

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
            'laterSuccessByQuestion',
            'adminAccepted',
            'attemptCountByQuestion',
            'scope'
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
        // Fail / düzeltmesiz satırlar toplu uygulamaya hiç girmez
        if ($review->status !== QuestionQualityReview::STATUS_REVIEWED) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Sadece başarılı incelemeler uygulanır.',
            ];
        }

        $question = $review->question;
        if (!$question) {
            return ['ok' => false, 'message' => 'İlişkili soru yok.'];
        }

        // Bu review zaten uygulanmışsa tekrar yazma
        if ($question->ai_accepted && (int) $question->ai_quality_review_id === (int) $review->id) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Bu inceleme zaten uygulanmış.',
            ];
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

        DB::transaction(function () use ($question, $en, $trOpts, $enOpts, $correctAnswer, $oldTr, $newTr, $review) {
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
            $question->ai_quality_review_id = (int) $review->id;
            // Uygulanan AI düzeltmesi = panelde kabul izi (işlem id bağlı)
            $question->ai_accepted = true;
            $question->save();

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'ai_review_apply_revision',
                'field' => 'question_content',
                'old_value' => mb_substr($oldTr, 0, 500),
                'new_value' => mb_substr($newTr, 0, 500)
                    . ' | correct=' . $correctAnswer
                    . ($oldCorrect !== $correctAnswer ? " (eski correct={$oldCorrect})" : '')
                    . ' | review_id=' . $review->id,
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

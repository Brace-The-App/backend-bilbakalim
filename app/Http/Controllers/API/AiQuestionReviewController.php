<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionQualityReview;
use App\Services\QuestionQualityReviewHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiQuestionReviewController extends Controller
{
    /**
     * AI için sıradaki soruyu verir (düzleştirilmiş key-value + statik prompt).
     */
    public function next(Request $request): JsonResponse
    {
        $this->expireStalePending();

        $review = DB::transaction(function () {
            $question = Question::query()
                ->with('category')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('question_quality_reviews as r')
                        ->whereColumn('r.question_id', 'questions.id')
                        ->whereIn('r.status', [
                            QuestionQualityReview::STATUS_PENDING,
                            QuestionQualityReview::STATUS_REVIEWED,
                        ]);
                })
                ->orderBy('questions.id')
                ->lockForUpdate()
                ->first();

            if (!$question) {
                return null;
            }

            $snapshot = $this->flattenQuestion($question);

            return QuestionQualityReview::query()->create([
                'question_id' => $question->id,
                'status' => QuestionQualityReview::STATUS_PENDING,
                'question_snapshot' => $snapshot,
                'assigned_at' => now(),
            ]);
        });

        if (!$review) {
            return $this->utf8Json([
                'success' => true,
                'message' => 'Kontrol bekleyen soru kalmadı.',
                'data' => null,
                'prompt' => QuestionQualityReviewHelper::prompt(),
            ]);
        }

        return $this->utf8Json([
            'success' => true,
            'message' => 'Soru atandı.',
            'data' => [
                'review_id' => $review->id,
                'assigned_at' => optional($review->assigned_at)?->toIso8601String(),
                'question' => $review->question_snapshot,
            ],
            'prompt' => QuestionQualityReviewHelper::prompt(),
        ]);
    }

    /**
     * AI çıktısını kaydeder. Beklenen gövde: { review_id, orjinal, analiz_sonucu }
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        $extracted = QuestionQualityReviewHelper::extractFromPayload($payload);

        $questionId = $extracted['question_id'];
        $reviewId = $extracted['review_id'];

        if (!$questionId) {
            throw ValidationException::withMessages([
                'question_id' => ['question_id (veya orjinal.question_id / analiz_sonucu.symbolCode) zorunlu.'],
            ]);
        }

        if (!Question::query()->whereKey($questionId)->exists()) {
            throw ValidationException::withMessages([
                'question_id' => ['Geçersiz question_id.'],
            ]);
        }

        if (!$reviewId) {
            throw ValidationException::withMessages([
                'review_id' => ['review_id zorunlu (GET yanıtından).'],
            ]);
        }

        /** @var QuestionQualityReview $review */
        $review = QuestionQualityReview::query()->find($reviewId);
        if (!$review) {
            throw ValidationException::withMessages([
                'review_id' => ['Geçersiz review_id.'],
            ]);
        }

        if ((int) $review->question_id !== (int) $questionId) {
            return $this->utf8Json([
                'success' => false,
                'message' => 'review_id ile question_id eşleşmiyor.',
            ], 422);
        }

        if ($review->status === QuestionQualityReview::STATUS_REVIEWED) {
            return $this->utf8Json([
                'success' => false,
                'message' => 'Bu review zaten tamamlanmış.',
                'data' => ['review_id' => $review->id, 'status' => $review->status],
            ], 409);
        }

        if ($review->status === QuestionQualityReview::STATUS_EXPIRED) {
            return $this->utf8Json([
                'success' => false,
                'message' => 'Bu review süresi dolmuş; GET ile yeni atama alın.',
                'data' => ['review_id' => $review->id, 'status' => $review->status],
            ], 409);
        }

        if ($extracted['failed']) {
            $review->fill([
                'status' => QuestionQualityReview::STATUS_FAILED,
                'provider' => $extracted['provider'] ?? $review->provider,
                'model' => $extracted['model'] ?? $review->model,
                'package' => $extracted['package'] ?? $review->package,
                'external_job_id' => $extracted['external_job_id'] ?? $review->external_job_id,
                'edit_reason' => $extracted['fail_reason'] ?? $extracted['edit_reason'] ?? $review->edit_reason,
                'raw_response' => $payload,
                'reviewed_at' => now(),
            ])->save();

            return $this->utf8Json([
                'success' => true,
                'message' => 'Review failed olarak kaydedildi.',
                'data' => [
                    'review_id' => $review->id,
                    'question_id' => $review->question_id,
                    'status' => $review->status,
                ],
            ]);
        }

        $review->fill([
            'status' => QuestionQualityReview::STATUS_REVIEWED,
            'provider' => $extracted['provider'],
            'model' => $extracted['model'],
            'package' => $extracted['package'],
            'external_job_id' => $extracted['external_job_id'],
            'quality_score' => $extracted['quality_score'],
            'quality_band' => $extracted['quality_band'],
            'recommended_action' => $extracted['recommended_action'],
            'estimated_difficulty' => $extracted['estimated_difficulty'],
            'boredom_risk' => $extracted['boredom_risk'],
            'ambiguity_risk' => $extracted['ambiguity_risk'],
            'duplicate_risk' => $extracted['duplicate_risk'],
            'knowledge_confidence' => $extracted['knowledge_confidence'],
            'criteria_scores' => $extracted['criteria_scores'],
            'edit_reason' => $extracted['edit_reason'],
            'revised_content' => $extracted['revised_content'],
            'raw_response' => $payload,
            'reviewed_at' => now(),
        ])->save();

        return $this->utf8Json([
            'success' => true,
            'message' => 'Review kaydedildi.',
            'data' => [
                'review_id' => $review->id,
                'question_id' => $review->question_id,
                'status' => $review->status,
                'quality_score' => $review->quality_score,
                'quality_band' => $review->quality_band,
                'recommended_action' => $review->recommended_action,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function utf8Json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(
            $payload,
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function expireStalePending(): void
    {
        $minutes = QuestionQualityReviewHelper::pendingTimeoutMinutes();

        QuestionQualityReview::query()
            ->where('status', QuestionQualityReview::STATUS_PENDING)
            ->where('assigned_at', '<', now()->subMinutes($minutes))
            ->update([
                'status' => QuestionQualityReview::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    /**
     * Prompt'taki GİRDİ yapısı (düzleştirilmiş). AI bunu orjinal altına aynen koyar.
     *
     * @return array<string, mixed>
     */
    private function flattenQuestion(Question $question): array
    {
        $cat = $question->category;
        $correctId = (string) $question->correct_answer;

        $choicesTr = [
            '1' => $question->getTranslation('one_choice', 'tr', false) ?: null,
            '2' => $question->getTranslation('two_choice', 'tr', false) ?: null,
            '3' => $question->getTranslation('three_choice', 'tr', false) ?: null,
            '4' => $question->getTranslation('four_choice', 'tr', false) ?: null,
        ];
        $choicesEn = [
            '1' => $question->getTranslation('one_choice', 'en', false) ?: null,
            '2' => $question->getTranslation('two_choice', 'en', false) ?: null,
            '3' => $question->getTranslation('three_choice', 'en', false) ?: null,
            '4' => $question->getTranslation('four_choice', 'en', false) ?: null,
        ];

        return [
            'question_id' => $question->id,
            'category_id' => $question->category_id,
            'category_tr' => $cat ? ($cat->getTranslation('name', 'tr', false) ?: null) : null,
            'category_en' => $cat ? ($cat->getTranslation('name', 'en', false) ?: null) : null,
            'question_tr' => $question->getTranslation('question', 'tr', false) ?: null,
            'question_en' => $question->getTranslation('question', 'en', false) ?: null,
            'choice1_id' => '1',
            'choice1_tr' => $choicesTr['1'],
            'choice1_en' => $choicesEn['1'],
            'choice2_id' => '2',
            'choice2_tr' => $choicesTr['2'],
            'choice2_en' => $choicesEn['2'],
            'choice3_id' => '3',
            'choice3_tr' => $choicesTr['3'],
            'choice3_en' => $choicesEn['3'],
            'choice4_id' => '4',
            'choice4_tr' => $choicesTr['4'],
            'choice4_en' => $choicesEn['4'],
            'correct_choice_id' => $correctId,
            'correct_choice_tr' => $choicesTr[$correctId] ?? null,
            'correct_choice_en' => $choicesEn[$correctId] ?? null,
        ];
    }
}

<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\TextBlock;
use App\Models\Question;
use App\Models\QuestionQualityReview;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClaudeQuestionReviewService
{
    public function apiKeyConfigured(): bool
    {
        return trim((string) config('services.anthropic.api_key', '')) !== '';
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model', 'claude-sonnet-4-5');
    }

    /** Panel / DB'de yazılan model adı (gerçek API modelinden farklı olabilir). */
    public function modelLabel(): string
    {
        return (string) config('services.anthropic.model_label', 'claude-opus-5');
    }

    public function expireStalePending(): void
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
     * Sıradaki soruyu pending olarak ata.
     */
    public function assignNext(): ?QuestionQualityReview
    {
        $this->expireStalePending();

        return DB::transaction(function () {
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
    }

    /**
     * Belirli soruyu pending olarak ata (pending/reviewed varsa yeniden denemez; expired/failed serbest).
     */
    public function assignQuestion(int $questionId): QuestionQualityReview
    {
        $this->expireStalePending();

        return DB::transaction(function () use ($questionId) {
            $question = Question::query()
                ->with('category')
                ->whereKey($questionId)
                ->lockForUpdate()
                ->first();

            if (!$question) {
                throw new RuntimeException("Soru #{$questionId} bulunamadı.");
            }

            $blocking = QuestionQualityReview::query()
                ->where('question_id', $questionId)
                ->whereIn('status', [
                    QuestionQualityReview::STATUS_PENDING,
                    QuestionQualityReview::STATUS_REVIEWED,
                ])
                ->lockForUpdate()
                ->first();

            if ($blocking) {
                if ($blocking->status === QuestionQualityReview::STATUS_PENDING) {
                    return $blocking;
                }
                throw new RuntimeException("Soru #{$questionId} zaten reviewed (#{$blocking->id}).");
            }

            $snapshot = $this->flattenQuestion($question);

            return QuestionQualityReview::query()->create([
                'question_id' => $question->id,
                'status' => QuestionQualityReview::STATUS_PENDING,
                'question_snapshot' => $snapshot,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function flattenQuestion(Question $question): array
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

    /**
     * Claude'a gönder; parse edilmiş {orjinal, analiz_sonucu} döner.
     *
     * @param  array<string, mixed>  $flatQuestion
     * @return array{raw_text:string, parsed:array<string, mixed>, model:string}
     */
    public function analyze(array $flatQuestion): array
    {
        if (!$this->apiKeyConfigured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY tanımlı değil.');
        }

        $model = $this->model();
        $modelLabel = $this->modelLabel();
        $maxTokens = max(256, (int) config('services.anthropic.max_tokens', 8192));

        $client = new Client(apiKey: (string) config('services.anthropic.api_key'));

        $userContent = "Aşağıdaki soru JSON'unu analiz et ve SADECE zorunlu çıktı JSON'unu döndür.\n\n"
            . json_encode($flatQuestion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $message = $client->messages->create(
            maxTokens: $maxTokens,
            messages: [
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            model: $model,
            system: QuestionQualityReviewHelper::prompt(),
        );

        $rawText = $this->extractText($message->content ?? []);
        $parsed = $this->parseJsonPayload($rawText);

        if (!isset($parsed['orjinal']) || !isset($parsed['analiz_sonucu'])) {
            throw new RuntimeException('Claude yanıtında orjinal / analiz_sonucu eksik.');
        }

        return [
            'raw_text' => $rawText,
            'parsed' => $parsed,
            'model' => $modelLabel,
            'api_model' => $model,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed  Claude JSON (orjinal + analiz_sonucu)
     * @param  array<string, mixed>  $meta
     */
    public function saveReviewed(QuestionQualityReview $review, array $parsed, array $meta = []): QuestionQualityReview
    {
        $payload = array_merge($parsed, [
            'review_id' => $review->id,
            'provider' => $meta['provider'] ?? 'anthropic',
            'model' => $meta['model'] ?? $this->modelLabel(),
            'package' => $meta['package'] ?? '4',
            'external_job_id' => $meta['external_job_id'] ?? null,
        ]);

        $extracted = QuestionQualityReviewHelper::extractFromPayload($payload);

        $questionId = $extracted['question_id'] ?? (int) $review->question_id;
        if ((int) $questionId !== (int) $review->question_id) {
            throw new RuntimeException('Claude question_id, atanmış review ile eşleşmiyor.');
        }

        $review->fill([
            'status' => QuestionQualityReview::STATUS_REVIEWED,
            'provider' => $extracted['provider'] ?? 'anthropic',
            'model' => $extracted['model'] ?? $this->modelLabel(),
            'package' => $extracted['package'] ?? '4',
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

        return $review->fresh();
    }

    public function markFailed(QuestionQualityReview $review, string $reason, array $meta = []): QuestionQualityReview
    {
        $review->fill([
            'status' => QuestionQualityReview::STATUS_FAILED,
            'provider' => $meta['provider'] ?? 'anthropic',
            'model' => $meta['model'] ?? $this->modelLabel(),
            'package' => $meta['package'] ?? '4',
            'edit_reason' => mb_substr($reason, 0, 2000),
            'raw_response' => [
                'failed' => true,
                'fail_reason' => $reason,
                'meta' => $meta,
            ],
            'reviewed_at' => now(),
        ])->save();

        return $review->fresh();
    }

    /**
     * @param  iterable<mixed>  $content
     */
    private function extractText(iterable $content): string
    {
        $parts = [];
        foreach ($content as $block) {
            if ($block instanceof TextBlock) {
                $parts[] = $block->text;
                continue;
            }
            if (is_object($block) && isset($block->type) && $block->type === 'text' && isset($block->text)) {
                $parts[] = (string) $block->text;
                continue;
            }
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $parts[] = (string) ($block['text'] ?? '');
            }
        }

        $text = trim(implode("\n", $parts));
        if ($text === '') {
            throw new RuntimeException('Claude boş metin döndü.');
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonPayload(string $rawText): array
    {
        $text = trim($rawText);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            $text = trim($m[1]);
        } else {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Claude yanıtı geçerli JSON değil: ' . json_last_error_msg());
        }

        return $decoded;
    }
}

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
    private ?string $lastRawText = null;

    public function apiKeyConfigured(): bool
    {
        return trim((string) config('services.anthropic.api_key', '')) !== '';
    }

    public function lastRawText(): ?string
    {
        return $this->lastRawText;
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
     * $retryFailed=true → önce fail olup henüz reviewed olmayanları yeniden dener.
     * $forceRetry=true → max_attempts aşılsa da failed yeniden atanır (manuel).
     * $retryFailedOnly=true → fail yoksa yeni soruya düşmez.
     */
    public function assignNext(bool $retryFailed = false, bool $forceRetry = false, bool $retryFailedOnly = false): ?QuestionQualityReview
    {
        $this->expireStalePending();

        if ($retryFailed) {
            $retry = $this->assignNextFailedRetry($forceRetry);
            if ($retry) {
                return $retry;
            }
            if ($retryFailedOnly) {
                return null;
            }
        }

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
                            QuestionQualityReview::STATUS_FAILED,
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
                'attempt' => 1,
                'previous_review_id' => null,
                'question_snapshot' => $snapshot,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * En son kaydı failed olan soruları yeniden ata.
     * $ignoreMaxAttempts=false → attempt < max_attempts olanlar (otomatik kapalıyken max=1 → hiçbiri).
     * $ignoreMaxAttempts=true → admin/manuel force retry.
     */
    public function assignNextFailedRetry(bool $ignoreMaxAttempts = false): ?QuestionQualityReview
    {
        $this->expireStalePending();
        $maxAttempts = max(1, (int) config('ai_question_review.max_attempts', 1));

        return DB::transaction(function () use ($maxAttempts, $ignoreMaxAttempts) {
            $failed = QuestionQualityReview::query()
                ->where('status', QuestionQualityReview::STATUS_FAILED)
                ->when(! $ignoreMaxAttempts, fn ($q) => $q->where('attempt', '<', $maxAttempts))
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('question_quality_reviews as r2')
                        ->whereColumn('r2.question_id', 'question_quality_reviews.question_id')
                        ->whereIn('r2.status', [
                            QuestionQualityReview::STATUS_PENDING,
                            QuestionQualityReview::STATUS_REVIEWED,
                        ]);
                })
                // Aynı soru için en yüksek attempt'li fail satırı
                ->whereIn('id', function ($q) {
                    $q->selectRaw('MAX(id)')
                        ->from('question_quality_reviews')
                        ->where('status', QuestionQualityReview::STATUS_FAILED)
                        ->groupBy('question_id');
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$failed) {
                return null;
            }

            $question = Question::query()
                ->with('category')
                ->whereKey($failed->question_id)
                ->lockForUpdate()
                ->first();

            if (!$question) {
                return null;
            }

            $snapshot = $this->flattenQuestion($question);
            $nextAttempt = max(1, (int) $failed->attempt) + 1;

            return QuestionQualityReview::query()->create([
                'question_id' => $question->id,
                'status' => QuestionQualityReview::STATUS_PENDING,
                'attempt' => $nextAttempt,
                'previous_review_id' => $failed->id,
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
                'attempt' => $this->nextAttemptForQuestion($questionId),
                'previous_review_id' => $this->lastFailedIdForQuestion($questionId),
                'question_snapshot' => $snapshot,
                'assigned_at' => now(),
            ]);
        });
    }

    private function nextAttemptForQuestion(int $questionId): int
    {
        $max = (int) QuestionQualityReview::query()
            ->where('question_id', $questionId)
            ->max('attempt');

        return max(1, $max + 1);
    }

    private function lastFailedIdForQuestion(int $questionId): ?int
    {
        $id = QuestionQualityReview::query()
            ->where('question_id', $questionId)
            ->where('status', QuestionQualityReview::STATUS_FAILED)
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
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
        $this->lastRawText = $rawText;
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

        $this->syncQuestionAiFlags($review->fresh());

        return $review->fresh();
    }

    /**
     * AI incelemesi tamamlanınca: check=1 + inceleme id.
     * ai_accepted yalnız admin AI düzeltmesini uygulayınca set edilir.
     * Admin kabulünde ai_quality_review_id uygulanmış incelemeyi gösterir; yeni
     * review o pointer'ı ezmez (aksi halde “Admin onaylı” listesi bozulur).
     */
    private function syncQuestionAiFlags(QuestionQualityReview $review): void
    {
        $question = Question::query()->find($review->question_id);
        if (!$question) {
            return;
        }

        $question->check = true;
        if (!$question->ai_accepted) {
            $question->ai_quality_review_id = (int) $review->id;
        }
        $question->save();
    }

    public function markFailed(QuestionQualityReview $review, string $reason, array $meta = [], ?string $rawText = null): QuestionQualityReview
    {
        $rawPayload = [
            'failed' => true,
            'fail_reason' => $reason,
            'meta' => $meta,
            'attempt' => (int) ($review->attempt ?? 1),
        ];
        if ($rawText !== null && $rawText !== '') {
            $rawPayload['raw_text_excerpt'] = mb_substr($rawText, 0, 8000);
            $rawPayload['raw_text_length'] = mb_strlen($rawText);
        }

        $review->fill([
            'status' => QuestionQualityReview::STATUS_FAILED,
            'provider' => $meta['provider'] ?? 'anthropic',
            'model' => $meta['model'] ?? $this->modelLabel(),
            'package' => $meta['package'] ?? '4',
            'edit_reason' => mb_substr($reason, 0, 2000),
            'raw_response' => $rawPayload,
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
        $candidates = $this->jsonCandidates($rawText);

        $lastError = 'boş yanıt';
        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $lastError = json_last_error_msg();

            foreach ($this->repairedJsonVariants($candidate) as $repaired) {
                if ($repaired === $candidate) {
                    continue;
                }
                $decoded = json_decode($repaired, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                $lastError = json_last_error_msg();
            }
        }

        throw new RuntimeException($this->formatJsonParseFailure($rawText, $lastError));
    }

    /**
     * Admin paneli için anlaşılır fail metni (ödeme / kota vb. yok).
     */
    private function formatJsonParseFailure(string $rawText, string $jsonError): string
    {
        $msg = 'Model çıktısı geçerli JSON olarak okunamadı.';

        // Tipik: ".... the "Schengen Visa" take ..." gibi kaçırılmamış tırnak
        if (preg_match('/:\s*"[^"\n]*"[A-Za-zÀ-ÿ]/u', $rawText)
            || preg_match('/"[A-Za-z][^"]{0,40}"[A-Za-z]/u', $rawText)
        ) {
            $msg .= ' Muhtemel neden: metin içindeki çift tırnak JSON’da kaçırılmamış (ör. İngilizce cümlede tırnaklı ifade).';
        } elseif (preg_match('/\\\\(?:sqrt|frac|times|div|cdot|pm|leq|geq|neq|rightarrow)/i', $rawText)
            || preg_match('/[^\\\\]\\\\[a-zA-Z]/', $rawText)
            || str_contains($rawText, '\\sqrt')
            || str_contains($rawText, '\\frac')
        ) {
            $msg .= ' Muhtemel neden: matematik/LaTeX ifadesindeki ters eğik çizgi (\\sqrt, \\frac vb.) JSON’da kaçırılmamış.';
        } elseif (stripos($jsonError, 'syntax') !== false) {
            $msg .= ' Muhtemel neden: bozuk veya yarım JSON sözdizimi (tırnak, matematik işareti veya kesik yanıt).';
        } elseif (stripos($jsonError, 'control') !== false) {
            $msg .= ' Muhtemel neden: metinde kaçırılmamış kontrol karakteri.';
        } else {
            $msg .= ' Yanıt şablona uymuyor veya kesilmiş olabilir.';
        }

        $msg .= ' Teknik: ' . $jsonError . '.';

        return $msg;
    }

    /**
     * @return list<string>
     */
    private function jsonCandidates(string $rawText): array
    {
        $text = trim($rawText);
        $out = [];

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $m)) {
            $out[] = trim($m[1]);
        }

        $balanced = $this->extractBalancedObject($text);
        if ($balanced !== null) {
            $out[] = $balanced;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $out[] = substr($text, $start, $end - $start + 1);
        }

        $out[] = $text;

        // unique preserve order
        $uniq = [];
        foreach ($out as $c) {
            $c = trim((string) $c);
            if ($c === '' || isset($uniq[$c])) {
                continue;
            }
            $uniq[$c] = true;
        }

        return array_keys($uniq);
    }

    private function extractBalancedObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                $depth++;
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Temel temizlik + tırnak / matematik-\\ onarım varyasyonları.
     *
     * @return list<string>
     */
    private function repairedJsonVariants(string $text): array
    {
        $base = $this->repairJsonBasics($text);
        $variants = [
            $base,
            $this->escapeInnerUnescapedQuotes($base),
            $this->escapeInvalidJsonBackslashes($base),
            $this->escapeInvalidJsonBackslashes($this->escapeInnerUnescapedQuotes($base)),
            $this->escapeInnerUnescapedQuotes($this->escapeInvalidJsonBackslashes($base)),
        ];

        $uniq = [];
        foreach ($variants as $v) {
            $v = trim((string) $v);
            if ($v === '' || isset($uniq[$v])) {
                continue;
            }
            $uniq[$v] = true;
        }

        return array_keys($uniq);
    }

    private function repairJsonBasics(string $text): string
    {
        $text = trim($text);
        // smart quotes
        $text = str_replace(
            ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}", '„', '‟', '‹', '›'],
            ['"', '"', "'", "'", '"', '"', "'", "'"],
            $text
        );
        // trailing commas before } or ]
        $text = preg_replace('/,\s*([}\]])/', '$1', $text) ?? $text;
        // PHP/JS style comments
        $text = preg_replace('~//[^\n]*~', '', $text) ?? $text;
        $text = preg_replace('~/\*.*?\*/~s', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * String değeri içindeki gerçek olmayan kapanış tırnaklarını \\" yap.
     * Kapanış: tırnaktan sonra (boşluk atlanarak) , } ] : veya EOF.
     */
    private function escapeInnerUnescapedQuotes(string $json): string
    {
        $len = strlen($json);
        $out = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];

            if (!$inString) {
                $out .= $ch;
                if ($ch === '"') {
                    $inString = true;
                }
                continue;
            }

            if ($escape) {
                $out .= $ch;
                $escape = false;
                continue;
            }

            if ($ch === '\\') {
                $out .= $ch;
                $escape = true;
                continue;
            }

            if ($ch === '"') {
                $j = $i + 1;
                while ($j < $len && ctype_space($json[$j])) {
                    $j++;
                }
                $next = $j < $len ? $json[$j] : '';
                if ($next === ',' || $next === '}' || $next === ']' || $next === ':' || $next === '') {
                    $out .= '"';
                    $inString = false;
                } else {
                    $out .= '\\"';
                }
                continue;
            }

            $out .= $ch;
        }

        return $out;
    }

    /**
     * JSON string içinde geçersiz kaçışları düzelt.
     * \sqrt \frac \times gibi LaTeX/matematik → \\sqrt ...
     * Geçerli kaçışlar korunur: \" \\ \/ \b \f \n \r \t \uXXXX
     */
    private function escapeInvalidJsonBackslashes(string $json): string
    {
        $len = strlen($json);
        $out = '';
        $inString = false;
        $i = 0;

        while ($i < $len) {
            $ch = $json[$i];

            if (!$inString) {
                $out .= $ch;
                if ($ch === '"') {
                    $inString = true;
                }
                $i++;
                continue;
            }

            if ($ch === '\\') {
                $next = ($i + 1 < $len) ? $json[$i + 1] : '';

                if ($next === '"' || $next === '\\' || $next === '/'
                    || $next === 'b' || $next === 'f' || $next === 'n'
                    || $next === 'r' || $next === 't') {
                    $out .= '\\' . $next;
                    $i += 2;
                    continue;
                }

                if ($next === 'u' && $i + 5 < $len
                    && preg_match('/^\\\\u[0-9a-fA-F]{4}/', substr($json, $i, 6))) {
                    $out .= substr($json, $i, 6);
                    $i += 6;
                    continue;
                }

                // \sqrt, \frac, \*, tek \ vb. → \\
                $out .= '\\\\';
                $i++;
                continue;
            }

            if ($ch === '"') {
                $j = $i + 1;
                while ($j < $len && ctype_space($json[$j])) {
                    $j++;
                }
                $next = $j < $len ? $json[$j] : '';
                $out .= '"';
                if ($next === ',' || $next === '}' || $next === ']' || $next === ':' || $next === '') {
                    $inString = false;
                }
                $i++;
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\QuestionQualityReview;
use App\Services\ClaudeQuestionReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class QuestionAiReviewCommand extends Command
{
    protected $signature = 'question:ai-review
        {--limit= : Bu çalıştırmada max soru (boşsa daily_limit kalanı)}
        {--question= : Tek soru ID (örn. 28962)}
        {--retry-failed : Sadece failed kayıtları yeniden dene}
        {--dry-run : Claude çağırır ama DB\'ye reviewed yazmaz (pending expire edilir)}
        {--package=4 : Teklif paket no (provider meta — Opus=4)}
        {--force : Günlük limiti yok say}';

    protected $description = 'Sıradaki soruları Claude ile kalite kontrolünden geçirir (gece job / manuel)';

    public function handle(ClaudeQuestionReviewService $claude): int
    {
        if (!$claude->apiKeyConfigured()) {
            $this->error('ANTHROPIC_API_KEY boş. .env dosyasına key ekleyip `php artisan config:clear` çalıştırın.');

            return self::FAILURE;
        }

        $questionId = $this->option('question') !== null && $this->option('question') !== ''
            ? (int) $this->option('question')
            : 0;

        $retryFailedOnly = (bool) $this->option('retry-failed');

        $dailyLimit = max(1, (int) config('ai_question_review.daily_limit', 100));
        $doneToday = $this->reviewedTodayCount();
        $remainingToday = max(0, $dailyLimit - $doneToday);

        if ($questionId <= 0 && !$this->option('force') && $remainingToday <= 0) {
            $this->warn("Günlük limit doldu ({$dailyLimit}). Bugün yapılan: {$doneToday}.");

            return self::SUCCESS;
        }

        if ($questionId > 0) {
            $limit = 1;
        } elseif ($this->option('limit') !== null && $this->option('limit') !== '') {
            $limit = max(1, (int) $this->option('limit'));
            if (!$this->option('force')) {
                $limit = min($limit, max(1, $remainingToday));
            }
        } else {
            $limit = $this->option('force') ? $dailyLimit : max(1, $remainingToday);
        }

        $dryRun = (bool) $this->option('dry-run');
        $package = (string) $this->option('package');
        $model = $claude->modelLabel();
        $maxAttempts = max(1, (int) config('ai_question_review.max_attempts', 3));

        $this->info(
            'Claude review başlıyor · model=' . $model
            . ' (api=' . $claude->model() . ')'
            . ($questionId > 0 ? " · question={$questionId}" : " · limit={$limit}")
            . ($retryFailedOnly ? ' · RETRY-FAILED' : '')
            . " · max_attempts={$maxAttempts}"
            . " · bugün={$doneToday}/{$dailyLimit}"
            . ($dryRun ? ' · DRY-RUN' : '')
        );

        $ok = 0;
        $fail = 0;

        for ($i = 0; $i < $limit; $i++) {
            if ($questionId <= 0 && !$this->option('force')) {
                $left = $dailyLimit - $this->reviewedTodayCount();
                if ($left <= 0) {
                    $this->warn('Günlük limite ulaşıldı, duruyor.');
                    break;
                }
            }

            try {
                if ($questionId > 0) {
                    $review = $claude->assignQuestion($questionId);
                } elseif ($retryFailedOnly) {
                    $review = $claude->assignNextFailedRetry();
                } else {
                    // Önce fail retry, yoksa yeni soru
                    $review = $claude->assignNext(true);
                }
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                $fail++;
                break;
            }

            if (!$review) {
                $this->warn($retryFailedOnly
                    ? 'Yeniden denenecek failed kayıt kalmadı.'
                    : 'Kontrol bekleyen soru kalmadı.');
                break;
            }

            $flat = is_array($review->question_snapshot)
                ? $review->question_snapshot
                : [];

            $attempt = (int) ($review->attempt ?? 1);
            $prev = $review->previous_review_id ? " · prev=#{$review->previous_review_id}" : '';
            $this->line("→ review #{$review->id} · question #{$review->question_id} · deneme {$attempt}/{$maxAttempts}{$prev}");

            try {
                $result = $claude->analyze($flat);
                $parsed = $result['parsed'];
                $meta = [
                    'provider' => 'anthropic',
                    'model' => $result['model'],
                    'package' => $package,
                ];

                if ($dryRun) {
                    $review->update([
                        'status' => 'expired',
                        'provider' => 'anthropic',
                        'model' => $result['model'],
                        'package' => $package,
                        'edit_reason' => 'dry-run: Claude yanıtı alındı, kaydedilmedi',
                        'raw_response' => [
                            'dry_run' => true,
                            'parsed' => $parsed,
                        ],
                        'reviewed_at' => now(),
                    ]);
                    $score = $parsed['analiz_sonucu']['ana_kalite_yuzdesi'] ?? '?';
                    $this->info("  dry-run OK · score={$score} (DB'ye reviewed yazılmadı, pending serbest)");
                    $ok++;
                    continue;
                }

                $saved = $claude->saveReviewed($review, $parsed, $meta);
                $retryNote = $attempt > 1 ? ' · önceki fail sonrası başarılı' : '';
                $this->info(
                    "  OK · score={$saved->quality_score} · band={$saved->quality_band} · action={$saved->recommended_action}{$retryNote}"
                );
                $ok++;
            } catch (Throwable $e) {
                $claude->markFailed($review, $e->getMessage(), [
                    'provider' => 'anthropic',
                    'model' => $model,
                    'package' => $package,
                ], $claude->lastRawText());
                $this->error('  FAIL · deneme ' . $attempt . ' · ' . $e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Bitti · ok={$ok} fail={$fail} · bugün=" . $this->reviewedTodayCount() . "/{$dailyLimit}");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reviewedTodayCount(): int
    {
        $start = Carbon::now('Europe/Istanbul')->startOfDay()->utc();
        $end = Carbon::now('Europe/Istanbul')->endOfDay()->utc();

        return (int) QuestionQualityReview::query()
            ->where('status', QuestionQualityReview::STATUS_REVIEWED)
            ->where('provider', 'anthropic')
            ->whereBetween('reviewed_at', [$start, $end])
            ->count();
    }
}

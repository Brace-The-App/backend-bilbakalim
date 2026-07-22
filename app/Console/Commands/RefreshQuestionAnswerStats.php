<?php

namespace App\Console\Commands;

use App\Http\Services\QuestionAnswerStatsService;
use Illuminate\Console\Command;

class RefreshQuestionAnswerStats extends Command
{
    protected $signature = 'questions:refresh-answer-stats {--question= : Belirli bir soru ID}';

    protected $description = 'Kullanıcı cevap istatistiklerini yeniden hesapla';

    public function handle(QuestionAnswerStatsService $service): int
    {
        $questionId = $this->option('question');

        if ($questionId) {
            $stat = $service->refreshQuestion((int) $questionId);
            $this->info("Soru #{$questionId} güncellendi. Toplam cevap: {$stat->total_answers}");
            return self::SUCCESS;
        }

        $this->info('Tüm soru istatistikleri hesaplanıyor...');
        $count = $service->refreshAll();
        $this->info("{$count} soru istatistiği güncellendi.");

        return self::SUCCESS;
    }
}

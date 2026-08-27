<?php

namespace App\Console\Commands;

use App\Http\Services\QuestionAnswerStatsService;
use Illuminate\Console\Command;

class AutoFixQuestionLevelMismatch extends Command
{
    protected $signature = 'questions:auto-fix-mismatch';

    protected $description = 'Güvenilir uyumsuz zorlukları açıksa dakikada bir soru düzeltir';

    public function handle(QuestionAnswerStatsService $service): int
    {
        if (!QuestionAnswerStatsService::isAutoFixMismatchEnabled()) {
            $this->line('Kapalı — atlandı.');

            return self::SUCCESS;
        }

        $fixed = $service->fixOneReliableMismatch();

        if ($fixed === null) {
            $this->line('Aday yok veya düzeltilecek kayıt kalmadı — atlandı.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Soru #%d: %s → %s',
            $fixed['question_id'],
            $fixed['old'],
            $fixed['new']
        ));

        return self::SUCCESS;
    }
}

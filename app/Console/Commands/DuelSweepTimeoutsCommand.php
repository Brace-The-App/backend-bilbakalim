<?php

namespace App\Console\Commands;

use App\Services\DuelTimeoutService;
use Illuminate\Console\Command;

class DuelSweepTimeoutsCommand extends Command
{
    protected $signature = 'duel:sweep-timeouts';

    protected $description = 'Aktif düellolarda cevap/bahis/sessizlik zaman aşımlarını tarar';

    public function handle(): int
    {
        $result = DuelTimeoutService::sweepAll();

        $parts = [];
        foreach (['answer_timeouts', 'pending_bet_timeouts', 'question_silence', 'waiting_stale'] as $key) {
            if (($result[$key] ?? 0) > 0) {
                $parts[] = "{$key}={$result[$key]}";
            }
        }

        $this->line($parts === [] ? 'Takılma yok.' : 'Kapatılan: '.implode(', ', $parts));

        return self::SUCCESS;
    }
}

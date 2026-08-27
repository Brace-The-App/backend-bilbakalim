<?php

namespace App\Console\Commands;

use App\Services\DuelBotSettings;
use Illuminate\Console\Command;

class DuelBotResetStuckCommand extends Command
{
    protected $signature = 'duel:bot-reset-stuck {--minutes=3 : Takılı sayılacak dakika eşiği}';

    protected $description = 'Takılı bot düello maçlarını kontrol eder ve gerekiyorsa otomatik kapatır';

    public function handle(): int
    {
        $minutes = max(3, (int) $this->option('minutes'));
        $result = DuelBotSettings::resetStuckBotMatchesIfNeeded($minutes);

        $this->line($result['message'] ?? 'Tamamlandı.');
        if (($result['closed'] ?? 0) > 0) {
            $this->info('Kapatılan düello ID: '.implode(', ', $result['duel_ids'] ?? []));
        }

        return self::SUCCESS;
    }
}

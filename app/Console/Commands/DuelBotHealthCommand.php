<?php

namespace App\Console\Commands;

use App\Services\DuelBotSettings;
use Illuminate\Console\Command;

/**
 * Root cron (schedule:run) üzerinden çalışır — FPM'de shell_exec kapalı olduğu için
 * acil reset / donmuş worker yenilemesi burada uygulanır.
 */
class DuelBotHealthCommand extends Command
{
    protected $signature = 'duel:bot-health
        {--stale-seconds=90 : Heartbeat bu sn’den eskiyse worker donmuş sayılır}
        {--force : İstek olmasa da pm2 restart dene}';

    protected $description = 'duel-bot PM2 sağlık: acil restart isteği + donmuş heartbeat kurtarma';

    public function handle(): int
    {
        $stale = max(30, (int) $this->option('stale-seconds'));
        $force = (bool) $this->option('force');

        $result = DuelBotSettings::applyDuelBotRestartIfNeeded($stale, $force);

        $action = (string) ($result['action'] ?? 'noop');
        $this->line($result['message'] ?? $action);

        if (! empty($result['output'])) {
            $this->line(mb_substr((string) $result['output'], 0, 300));
        }

        return ($result['success'] ?? true) ? self::SUCCESS : self::FAILURE;
    }
}

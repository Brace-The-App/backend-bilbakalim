<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // Test mail gönderme - her dakika çalışır (test amaçlı)
        // $schedule->command('mail:send-test eyupinan08@gmail.com')
        //        ->everyMinute()
        //      ->withoutOverlapping()
        //      ->runInBackground();

        // Kullanıcı cevap istatistiklerini periyodik güncelle
        $schedule->command('questions:refresh-answer-stats')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Not: asıl schedule bootstrap/app.php withSchedule içinde.
        // questions:auto-fix-mismatch orada everyMinute kayıtlı.

        // Gece Claude soru kalite kontrolü — günde max daily_limit (varsayılan 250)
        // Fail otomatik yeniden denenmez (max_attempts=1); manuel: question:ai-review --retry-failed --force-retry
        $at = (string) config('ai_question_review.schedule_at', '02:00');
        $dailyLimit = max(1, (int) config('ai_question_review.daily_limit', 250));
        $schedule->command("question:ai-review --limit={$dailyLimit}")
            ->dailyAt($at)
            ->timezone('Europe/Istanbul')
            ->withoutOverlapping(240)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/question-ai-review.log'));

        // Eski: 14:30 otomatik fail retry — API maliyeti / kod hatası riski için kapalı
        // $schedule->command('question:ai-review --retry-failed --limit=20')
        //     ->dailyAt('14:30')
        //     ->timezone('Europe/Istanbul')
        //     ->withoutOverlapping(120)
        //     ->runInBackground()
        //     ->appendOutputTo(storage_path('logs/question-ai-review.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

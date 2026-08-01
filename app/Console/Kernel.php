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

        // Gece Claude soru kalite kontrolü (bootstrap/app.php ile aynı; Laravel 11 schedule oradan da okunur)
        $at = (string) config('ai_question_review.schedule_at', '02:00');
        $schedule->command('question:ai-review --limit=100')
            ->dailyAt($at)
            ->timezone('Europe/Istanbul')
            ->withoutOverlapping(240)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/question-ai-review.log'));
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

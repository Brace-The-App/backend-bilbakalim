<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('questions:refresh-answer-stats')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Gece Claude soru kalite kontrolü — günde max daily_limit (varsayılan 250)
        $at = (string) config('ai_question_review.schedule_at', '02:00');
        $dailyLimit = max(1, (int) config('ai_question_review.daily_limit', 250));
        $schedule->command("question:ai-review --limit={$dailyLimit}")
            ->dailyAt($at)
            ->timezone('Europe/Istanbul')
            ->withoutOverlapping(240)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/question-ai-review.log'));

        $schedule->command('question:ai-review --retry-failed --limit=20')
            ->dailyAt('14:30')
            ->timezone('Europe/Istanbul')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/question-ai-review.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceJsonAcceptForApi::class,
            \App\Http\Middleware\UpdateLastActiveAt::class,
        ]);

        $middleware->alias([
            'ai.questions.token' => \App\Http\Middleware\AiQuestionsTokenMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $headers = $e->getHeaders();
            $retryAfter = isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null;

            return response()->json([
                'success' => false,
                'message' => 'Çok fazla istek attınız. Lütfen daha sonra tekrar deneyin.',
                'retry_after_seconds' => $retryAfter,
            ], 429);
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            $public = \App\Services\QuestionQualityReviewHelper::publicFailReasonFromThrowable($e);
            if ($public === null) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $public,
                ], 503);
            }

            return response($public, 503);
        });
    })->create();

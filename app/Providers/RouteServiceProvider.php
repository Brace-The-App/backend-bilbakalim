<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reward-claim', function (Request $request) {
            return Limit::perMinute(5)->by('reward-claim:' . ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('store-purchase', function (Request $request) {
            return Limit::perMinute(10)->by('store-purchase:' . ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('ai-questions', function (Request $request) {
            $token = (string) $request->header('X-AI-TOKEN', '');
            $key = $token !== '' ? 'ai:' . sha1($token) : 'ai:' . $request->ip();

            return Limit::perMinute((int) env('AI_QUESTIONS_MAX_REQUESTS_PER_MINUTE', 1))->by($key);
        });

        RateLimiter::for('ai-question-reviews', function (Request $request) {
            $token = (string) $request->header('X-AI-TOKEN', '');
            $key = $token !== '' ? 'ai-review:' . sha1($token) : 'ai-review:' . $request->ip();

            return Limit::perMinute((int) env('AI_QUESTION_REVIEW_MAX_PER_MINUTE', 60))->by($key);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}

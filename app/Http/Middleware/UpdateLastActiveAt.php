<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActiveAt
{
    /**
     * Authenticated API isteklerinde last_active_at'i en fazla dakikada bir günceller.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (!$user) {
            return $response;
        }

        $cacheKey = 'user_last_active_touch_' . $user->id;
        if (Cache::add($cacheKey, 1, now()->addSeconds(60))) {
            $user->newQuery()
                ->whereKey($user->id)
                ->update(['last_active_at' => now()]);
        }

        return $response;
    }
}

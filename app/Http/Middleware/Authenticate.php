<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API route'larında her zaman JSON (401) dönülsün, 302 redirect olmasın
        if ($request->is('api/*')) {
            return null;
        }
        return $request->expectsJson() ? null : route('login');
    }
}

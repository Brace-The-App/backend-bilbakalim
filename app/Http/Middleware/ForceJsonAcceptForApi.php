<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class ForceJsonAcceptForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $accept = (string) $request->header('Accept', '');
        if ($accept === '' || $accept === '*/*' || ! str_contains(strtolower($accept), 'json')) {
            $request->headers->set('Accept', 'application/json', true);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiQuestionsTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-AI-TOKEN', '');
        $expected = (string) config('services.ai_questions.token', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}


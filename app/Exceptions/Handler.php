<?php

namespace App\Exceptions;

use Abbasudo\Purity\Exceptions\FieldNotSupported;
use App\Http\Custom\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return (new Response())->unauthenticated();
            }
        });

        $this->renderable(function (FieldNotSupported $e, $request) {
            if ($request->is('api/*')) {
                return (new Response())->error([],$e->getMessage());
            }
        });
    }
}

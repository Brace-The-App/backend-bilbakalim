<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $limit = request()->input('limit');
        Config::set(['limit' =>  $limit ?? env('PER_PAGE')]);

        Response::macro('notFound', function ($message) {
            return Response::make(compact('message'), \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND);
        });
    }
}

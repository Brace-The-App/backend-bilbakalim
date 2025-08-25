<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Middleware\AccountMiddleware;


Route::group(['prefix' => 'auth'], function () {
    //auth
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::group(['middleware' => ['auth:sanctum', AccountMiddleware::class]], function () {
    //auth
    Route::post('me/update', [AuthController::class, 'edit']);
    Route::get('me', [AuthController::class, 'detail']);
});

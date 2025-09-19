<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\QuestionController;

// Auth routes (no middleware)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes (auth:sanctum middleware)
Route::middleware('auth:sanctum')->group(function () {
    // Auth protected routes
    Route::post('me/update', [AuthController::class, 'edit']);
    Route::get('auth/me', [AuthController::class, 'detail']);
    Route::post('logout', [AuthController::class, 'logout']);
    
    // Categories
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    
    // Questions
    Route::get('questions/{id}', [QuestionController::class, 'show']);
    Route::post('questions', [QuestionController::class, 'store']);
    Route::put('questions/{id}', [QuestionController::class, 'update']);
    Route::delete('questions/{id}', [QuestionController::class, 'destroy']);
    Route::get('categories/{categoryId}/questions', [QuestionController::class, 'byCategory']);
});
Route::get('questions', [QuestionController::class, 'index']);

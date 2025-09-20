<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\QuestionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\IndividualGameController;
use App\Http\Controllers\API\GameSessionController;
use App\Http\Controllers\API\GameAnswerController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\CoinPackageController;
use App\Http\Controllers\API\CoinPurchaseController;
use App\Http\Controllers\API\TournamentController;
use App\Http\Controllers\API\TournamentUserController;
use App\Http\Controllers\API\FriendInviteController;

// Auth routes (no middleware)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Password Reset routes (no middleware)
Route::prefix('password-reset')->group(function () {
    Route::post('send-code', [PasswordResetController::class, 'sendCode']);
    Route::post('verify-code', [PasswordResetController::class, 'verifyCode']);
    Route::post('reset', [PasswordResetController::class, 'resetPassword']);
    Route::post('check-identifier', [PasswordResetController::class, 'checkIdentifier']);
});

// Verification routes (no middleware)
Route::prefix('verification')->group(function () {
    Route::post('send-code', [VerificationController::class, 'sendCode']);
    Route::post('verify', [VerificationController::class, 'verify']);
    Route::post('resend', [VerificationController::class, 'resend']);
    Route::get('status/{identifier}', [VerificationController::class, 'checkStatus']);
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
    
    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::post('send', [NotificationController::class, 'send']);
        Route::get('stats', [NotificationController::class, 'stats']);
        Route::get('recent', [NotificationController::class, 'recent']);
    });
    
    // Individual Game routes
    Route::prefix('individual-games')->group(function () {
        Route::post('create', [IndividualGameController::class, 'create']);
        Route::get('active', [IndividualGameController::class, 'getActiveGame']);
        Route::post('start', [IndividualGameController::class, 'startGame']);
        Route::post('complete', [IndividualGameController::class, 'completeGame']);
        Route::post('abandon', [IndividualGameController::class, 'abandonGame']);
        Route::get('history', [IndividualGameController::class, 'gameHistory']);
        Route::get('stats', [IndividualGameController::class, 'gameStats']);
    });
    
    // Game Session routes
    Route::prefix('game-sessions')->group(function () {
        Route::post('create', [GameSessionController::class, 'create']);
        Route::get('active', [GameSessionController::class, 'getActiveSession']);
        Route::get('next-question', [GameSessionController::class, 'getNextQuestion']);
        Route::post('complete', [GameSessionController::class, 'completeSession']);
        Route::post('abandon', [GameSessionController::class, 'abandonSession']);
        Route::get('stats', [GameSessionController::class, 'getSessionStats']);
    });
    
    // Game Answer routes
    Route::prefix('game-answers')->group(function () {
        Route::post('submit', [GameAnswerController::class, 'submitAnswer']);
        Route::get('history', [GameAnswerController::class, 'getAnswerHistory']);
    });
    
    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('initiate', [PaymentController::class, 'initiatePayment']);
        Route::get('status/{payment_id}', [PaymentController::class, 'checkPaymentStatus']);
        Route::post('webhook', [PaymentController::class, 'paymentWebhook']);
        Route::get('history', [PaymentController::class, 'paymentHistory']);
        Route::post('cancel', [PaymentController::class, 'cancelPayment']);
    });
    
    // Coin Package routes
    Route::prefix('coin-packages')->group(function () {
        Route::get('/', [CoinPackageController::class, 'index']);
        Route::get('popular', [CoinPackageController::class, 'popular']);
        Route::get('{coinPackage}', [CoinPackageController::class, 'show']);
        Route::post('/', [CoinPackageController::class, 'store']); // Admin only
        Route::put('{coinPackage}', [CoinPackageController::class, 'update']); // Admin only
        Route::delete('{coinPackage}', [CoinPackageController::class, 'destroy']); // Admin only
        Route::get('{coinPackage}/stats', [CoinPackageController::class, 'stats']); // Admin only
    });
    
    // Coin Purchase routes
    Route::prefix('coin-purchases')->group(function () {
        Route::get('/', [CoinPurchaseController::class, 'index']);
        Route::get('{coinPurchase}', [CoinPurchaseController::class, 'show']);
        Route::get('stats/total', [CoinPurchaseController::class, 'totalPurchased']);
        Route::get('stats/monthly', [CoinPurchaseController::class, 'monthlyStats']);
        Route::post('{coinPurchase}/refund', [CoinPurchaseController::class, 'requestRefund']);
        Route::post('{coinPurchase}/cancel', [CoinPurchaseController::class, 'cancel']);
    });
    
    // Tournament routes
    Route::prefix('tournaments')->group(function () {
        Route::get('/', [TournamentController::class, 'index']);
        Route::get('{tournament}', [TournamentController::class, 'show']);
        Route::post('{tournament}/join', [TournamentController::class, 'join']);
        Route::post('{tournament}/leave', [TournamentController::class, 'leave']);
        Route::get('{tournament}/leaderboard', [TournamentController::class, 'leaderboard']);
        Route::get('user/history', [TournamentController::class, 'userHistory']);
        Route::post('{tournament}/start', [TournamentController::class, 'start']); // Admin only
        Route::post('{tournament}/finish', [TournamentController::class, 'finish']); // Admin only
    });
    
    // Tournament User routes
    Route::prefix('tournament-users')->group(function () {
        Route::get('/', [TournamentUserController::class, 'index']);
        Route::get('{tournamentUser}', [TournamentUserController::class, 'show']);
        Route::post('{tournament}/start-game', [TournamentUserController::class, 'startGame']);
        Route::post('submit-answer', [TournamentUserController::class, 'submitAnswer']);
        Route::post('complete-game', [TournamentUserController::class, 'completeGame']);
    });
    
    // Friend Invite routes
    Route::prefix('friend-invites')->group(function () {
        Route::post('create', [FriendInviteController::class, 'create']);
        Route::get('my-invites', [FriendInviteController::class, 'myInvites']);
        Route::get('stats', [FriendInviteController::class, 'stats']);
        Route::post('accept/{inviteCode}', [FriendInviteController::class, 'accept']);
    });
});

// Question routes (public)
Route::prefix('questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index']);
    Route::get('categories', [QuestionController::class, 'categories']);
    Route::get('for-game', [QuestionController::class, 'forGame']);
    Route::get('random', [QuestionController::class, 'random']);
    Route::get('{id}', [QuestionController::class, 'show']);
    Route::get('category/{categoryId}', [QuestionController::class, 'byCategory']);
});

// Categories routes (public)
Route::prefix('categories')->group(function () {
    Route::get('/', [QuestionController::class, 'categories']);
});

// Public game routes
Route::prefix('game')->group(function () {
    Route::get('questions/for-game', [QuestionController::class, 'forGame']);
    Route::get('questions/random', [QuestionController::class, 'random']);
});

// Public tournament test routes
Route::prefix('tournaments')->group(function () {
    Route::get('test/status', [TournamentController::class, 'testStatus']);
    Route::post('{tournament}/test-start', [TournamentController::class, 'testStart']);
});

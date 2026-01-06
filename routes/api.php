<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\CoinPackageController;
use App\Http\Controllers\API\CoinPurchaseController;
use App\Http\Controllers\API\FriendInviteController;
use App\Http\Controllers\API\QuizController;
use App\Http\Controllers\API\PremiumQuizController;
use App\Http\Controllers\API\TournamentQuizController;
use App\Http\Controllers\API\LandingController;
use App\Http\Controllers\API\DuelController;
use App\Http\Controllers\API\DiamondController;
use App\Http\Controllers\API\AvatarController;
use App\Http\Controllers\API\AdWatchController;
use App\Http\Controllers\API\CoinHistoryController;
use App\Http\Controllers\API\GiftCardStoreController;
use App\Http\Controllers\API\LeaderboardController;
use App\Http\Controllers\API\RewardController;

// Auth routes (no middleware)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Referral routes (auth required)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('referral')->group(function () {
        Route::get('my-code', [AuthController::class, 'getMyReferralCode']);
        Route::get('can-use', [AuthController::class, 'canUseReferralCode']);
    });
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


    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::post('send', [NotificationController::class, 'send']);
        Route::get('stats', [NotificationController::class, 'stats']);
        Route::get('recent', [NotificationController::class, 'recent']);
    });


    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('initiate', [PaymentController::class, 'initiatePayment']);
        Route::get('status/{payment_id}', [PaymentController::class, 'checkPaymentStatus']);
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
        Route::post('purchase', [CoinPurchaseController::class, 'purchase']);
        Route::post('{coinPurchase}/refund', [CoinPurchaseController::class, 'requestRefund']);
        Route::post('{coinPurchase}/cancel', [CoinPurchaseController::class, 'cancel']);
    });



    // Friend Invite routes
    Route::prefix('friend-invites')->group(function () {
        Route::post('create', [FriendInviteController::class, 'create']);
        Route::get('my-invites', [FriendInviteController::class, 'myInvites']);
        Route::get('stats', [FriendInviteController::class, 'stats']);
        Route::post('accept/{inviteCode}', [FriendInviteController::class, 'accept']);
    });

    // Game Settings route
    Route::get('game-settings', [QuizController::class, 'getGameSettings']);

    // Quiz routes
    Route::prefix('quiz')->group(function () {
        // Normal Quiz (Sonsuz Mod)
        Route::post('normal/start', [QuizController::class, 'startNormalQuiz']);
        Route::post('normal/answer', [QuizController::class, 'submitAnswer']);
        Route::post('normal/end', [QuizController::class, 'endNormalQuiz']);
        Route::get('normal/history', [QuizController::class, 'getGameHistory']);
        Route::get('normal/details/{game_id}', [QuizController::class, 'getGameDetails']);
        Route::get('normal/jokers', [QuizController::class, 'getJokers']);
        Route::post('normal/use-joker', [QuizController::class, 'useJoker']);
        Route::post('normal/buy-joker', [QuizController::class, 'buyJoker']);

        // Normal Quiz Mobile APIs
        Route::post('normal/mobile/start', [QuizController::class, 'startMobileNormalQuiz']);
        Route::post('normal/mobile/submit-answers', [QuizController::class, 'submitMobileNormalAnswers']);

        // Premium Quiz
        Route::post('premium/start', [PremiumQuizController::class, 'startPremiumQuiz']);
        Route::post('premium/answer', [PremiumQuizController::class, 'submitAnswer']);
        Route::post('premium/joker', [PremiumQuizController::class, 'useJoker']);
        Route::post('premium/end', [PremiumQuizController::class, 'endPremiumQuiz']);
        Route::get('premium/details/{game_id}', [PremiumQuizController::class, 'getGameDetails']);
        Route::get('premium/jokers', [PremiumQuizController::class, 'getUserJokers']);
        Route::post('premium/buy-joker', [PremiumQuizController::class, 'buyJoker']);

        // Premium Quiz Mobile APIs
        Route::post('premium/mobile/start', [PremiumQuizController::class, 'startMobilePremiumQuiz']);
        Route::post('premium/mobile/submit-answers', [PremiumQuizController::class, 'submitMobilePremiumAnswers']);
    });

    // Tournament Quiz routes
    Route::prefix('tournament-quiz')->group(function () {
        Route::post('create-or-join', [TournamentQuizController::class, 'createOrJoinTournament']);
        Route::post('join', [TournamentQuizController::class, 'joinTournament']);
        Route::post('leave', [TournamentQuizController::class, 'leaveTournament']);
        Route::post('start', [TournamentQuizController::class, 'startTournament']); // Admin only
        Route::post('answer', [TournamentQuizController::class, 'submitTournamentAnswer']);
        Route::get('status/{tournament_id}', [TournamentQuizController::class, 'getTournamentStatus']);
        Route::get('results/{tournament_id}', [TournamentQuizController::class, 'getTournamentResults']);
        Route::get('questions/{tournament_id}', [TournamentQuizController::class, 'getTournamentQuestions']);
        Route::post('check-time', [TournamentQuizController::class, 'checkTournamentTime']);
        Route::get('waiting-status/{tournament_id}', [TournamentQuizController::class, 'getWaitingStatus']);

        // Yeni endpoint'ler
        Route::get('active-multiplayer', [TournamentQuizController::class, 'getActiveMultiplayerTournaments']);
        Route::get('question-based', [TournamentQuizController::class, 'getQuestionBasedTournaments']);
    });

    // Duel (Meydan Okuma) routes
    Route::prefix('duel')->group(function () {
        Route::post('create', [DuelController::class, 'create']);
        Route::get('status/{duel_id}', [DuelController::class, 'status']);
        Route::post('accept/{duel_id}', [DuelController::class, 'accept']);
        Route::post('reject/{duel_id}', [DuelController::class, 'reject']);
        Route::post('leave/{duel_id}', [DuelController::class, 'leave']);
        Route::post('answer/{duel_id}', [DuelController::class, 'submitAnswer']);
    });

    // Diamond (Elmas) routes
    Route::prefix('diamond')->group(function () {
        Route::get('balance', [DiamondController::class, 'getBalance']);
        Route::get('packages', [DiamondController::class, 'getPackages']);
        Route::post('purchase', [DiamondController::class, 'purchase']);
        Route::post('transfer-to-card', [DiamondController::class, 'transferToCard']);
    });

    // Avatar routes
    Route::get('avatars', [AvatarController::class, 'index']);

    // Ad Watch routes
    Route::prefix('ad-watch')->group(function () {
        Route::post('reward', [AdWatchController::class, 'reward']);
    });

    // Coin History routes
    Route::get('coin-history', [CoinHistoryController::class, 'index']);

    // Gift Card Stores routes
    Route::get('gift-card-stores', [GiftCardStoreController::class, 'index']);

    // Leaderboard routes
    Route::prefix('leaderboard')->group(function () {
        Route::get('/', [LeaderboardController::class, 'all']);
        Route::get('daily', [LeaderboardController::class, 'daily']);
        Route::get('weekly', [LeaderboardController::class, 'weekly']);
    });

    // Reward routes
    Route::prefix('reward')->group(function () {
        Route::get('check-eligibility', [RewardController::class, 'checkEligibility']);
        Route::post('claim', [RewardController::class, 'claim']);
    });
});

// Landing Page API routes (no auth required)
Route::prefix('landing')->group(function () {
    Route::get('about', [LandingController::class, 'about']);
    Route::get('news', [LandingController::class, 'news']);
    Route::get('testimonials', [LandingController::class, 'testimonials']);
    Route::get('features', [LandingController::class, 'features']);
    Route::get('benefits', [LandingController::class, 'benefits']);
    Route::get('faqs', [LandingController::class, 'faqs']);
    Route::get('all', [LandingController::class, 'all']);
});

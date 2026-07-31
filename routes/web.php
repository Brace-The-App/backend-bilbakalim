<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuestionAnswerStatsController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\LandingAboutController;
use App\Http\Controllers\Admin\LandingFeatureController;
use App\Http\Controllers\Admin\LandingBenefitController;
use App\Http\Controllers\Admin\LandingTestimonialController;
use App\Http\Controllers\Admin\LandingFaqController;
use App\Http\Controllers\Admin\LandingNewsController;
use App\Http\Controllers\Admin\LandingV2Controller;
use App\Http\Controllers\Admin\SmsVitriniTestController;
use App\Http\Controllers\Admin\DuelBotController;

// Welcome page
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Admin login redirect
Route::get('/admin', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
})->name('admin');

// Auth routes - Login & Logout
Route::get('/login', function () {
    return view('admin.auth.login');
})->name('login')->middleware('guest');

Route::post('/login', [App\Http\Controllers\API\Auth\AuthController::class, 'login_post'])->name('login.post');

Route::post('/logout', function () {
    auth()->logout();
    return redirect()->route('welcome');
})->name('logout');

// Admin panel routes
Route::prefix('/admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sms-vitrini-test', SmsVitriniTestController::class)->name('sms-vitrini-test');

    // Users management
    Route::resource('users', UserController::class);
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // Categories management
    Route::resource('categories', CategoryController::class);

    // Questions management
    Route::post('questions/{question}/toggle-check', [QuestionController::class, 'toggleCheck'])->name('questions.toggle-check');
    Route::post('questions/{question}/toggle-active', [QuestionController::class, 'toggleActive'])->name('questions.toggle-active');
    Route::resource('questions', QuestionController::class);

    // Kullanıcı cevap istatistikleri
    Route::get('question-answer-stats', [QuestionAnswerStatsController::class, 'index'])->name('question-answer-stats.index');
    Route::post('question-answer-stats/refresh', [QuestionAnswerStatsController::class, 'refresh'])->name('question-answer-stats.refresh');
    Route::patch('question-answer-stats/{question}/level', [QuestionAnswerStatsController::class, 'updateLevel'])->name('question-answer-stats.update-level');
    Route::patch('question-answer-stats/{question}/status', [QuestionAnswerStatsController::class, 'updateStatus'])->name('question-answer-stats.update-status');
    Route::get('question-answer-stats/{question}/detail', [QuestionAnswerStatsController::class, 'showDetail'])->name('question-answer-stats.detail');
    Route::get('question-answer-stats/{question}/options/{option}/answers', [QuestionAnswerStatsController::class, 'optionAnswers'])->name('question-answer-stats.option-answers');
    Route::get('question-answer-stats/{question}/logs', [QuestionAnswerStatsController::class, 'showLogs'])->name('question-answer-stats.logs');

    // Tournaments management
    Route::resource('tournaments', TournamentController::class);

    // General Settings management
    Route::resource('general-settings', GeneralSettingController::class);
    Route::post('general-settings/upload-logo', [GeneralSettingController::class, 'uploadLogo'])->name('general-settings.upload-logo');
    Route::post('general-settings/upload-favicon', [GeneralSettingController::class, 'uploadFavicon'])->name('general-settings.upload-favicon');

    // Permission management
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('permissions/roles', [PermissionController::class, 'createRole'])->name('permissions.roles.create');
    Route::put('permissions/roles/{role}', [PermissionController::class, 'updateRole'])->name('permissions.roles.update');
    Route::delete('permissions/roles/{role}', [PermissionController::class, 'destroyRole'])->name('permissions.roles.destroy');
    Route::post('permissions/roles/{role}/permissions', [PermissionController::class, 'updateRolePermissions'])->name('permissions.roles.permissions.update');

    // Notifications management
    Route::resource('notifications', NotificationController::class);
    Route::post('notifications/send', [NotificationController::class, 'send'])->name('notifications.send');

    // Avatars management
    Route::resource('avatars', \App\Http\Controllers\Admin\AvatarController::class);

    // Gift Card Stores management
    Route::resource('gift-card-stores', \App\Http\Controllers\Admin\GiftCardStoreController::class);

    // Ads (reklam görselleri)
    Route::resource('ads', \App\Http\Controllers\Admin\AdController::class)->only(['index', 'store', 'update', 'destroy']);

    // Düello bot ayarları (şimdilik kısıtlı erişim — controller içinde)
    Route::get('duel-bot', [DuelBotController::class, 'index'])->name('duel-bot.index');
    Route::get('duel-bot/logs', [DuelBotController::class, 'logs'])->name('duel-bot.logs');
    Route::get('duel-bot/live', [DuelBotController::class, 'live'])->name('duel-bot.live');
    Route::get('duel-bot/{userId}/duels', [DuelBotController::class, 'duels'])->name('duel-bot.duels')->whereNumber('userId');
    Route::get('duel-bot/{userId}/duels/{duelId}', [DuelBotController::class, 'duelDetail'])->name('duel-bot.duel-detail')->whereNumber('userId')->whereNumber('duelId');
    Route::post('duel-bot', [DuelBotController::class, 'store'])->name('duel-bot.store');
    Route::post('duel-bot/logs/clear', [DuelBotController::class, 'clearLogs'])->name('duel-bot.logs.clear');
    Route::post('duel-bot/active', [DuelBotController::class, 'updateActive'])->name('duel-bot.active');
    Route::post('duel-bot/behavior', [DuelBotController::class, 'updateBehavior'])->name('duel-bot.behavior');
    Route::post('duel-bot/matchmaking', [DuelBotController::class, 'updateMatchmaking'])->name('duel-bot.matchmaking');
    Route::put('duel-bot/profile', [DuelBotController::class, 'updateProfile'])->name('duel-bot.profile');
    Route::put('duel-bot/avatar', [DuelBotController::class, 'updateAvatar'])->name('duel-bot.avatar');
    Route::put('duel-bot', [DuelBotController::class, 'update'])->name('duel-bot.update');

    // Reward Requests management
    Route::get('reward-requests', [\App\Http\Controllers\Admin\RewardRequestController::class, 'index'])->name('reward-requests.index');
    Route::post('reward-requests/{rewardRequest}/approve', [\App\Http\Controllers\Admin\RewardRequestController::class, 'approve'])->name('reward-requests.approve');
    Route::post('reward-requests/{rewardRequest}/reject', [\App\Http\Controllers\Admin\RewardRequestController::class, 'reject'])->name('reward-requests.reject');
    Route::delete('reward-requests/{rewardRequest}', [\App\Http\Controllers\Admin\RewardRequestController::class, 'destroy'])->name('reward-requests.destroy');

    // Landing - üst grup
    Route::prefix('landing')->name('landing.')->group(function () {
        Route::resource('about', LandingAboutController::class)->parameters(['about' => 'about']);
        Route::resource('features', LandingFeatureController::class)->parameters(['features' => 'feature']);
        Route::resource('benefits', LandingBenefitController::class)->parameters(['benefits' => 'benefit']);
        Route::resource('testimonials', LandingTestimonialController::class)->parameters(['testimonials' => 'testimonial']);
        Route::resource('faqs', LandingFaqController::class)->parameters(['faqs' => 'faq']);
        Route::resource('news', LandingNewsController::class)->parameters(['news' => 'news']);

        Route::get('v2', [LandingV2Controller::class, 'index'])->name('v2.index');
        Route::match(['get', 'post'], 'v2/editor', [LandingV2Controller::class, 'editor'])->name('v2.editor');
    });
});

Route::get('/api/documentation', function () {
    $documentation = 'default';
    $documentationTitle = 'BilBakalim API Documentation';
    $useAbsolutePath = true;
    $docsPath = storage_path('api-docs/api-docs.json');
    $version = file_exists($docsPath) ? filemtime($docsPath) : time();
    $urlsToDocs = [
        'BilBakalim API' => url('/docs/api-docs.json') . '?v=' . $version,
    ];

    return view('l5-swagger::index', compact('documentation', 'documentationTitle', 'urlsToDocs', 'useAbsolutePath'));
})->name('l5-swagger.default.docs');

Route::get('/docs/api-docs.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('l5-swagger.default.docs.json');







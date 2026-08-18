<?php

use App\Http\Controllers\AppDownloadController;
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
use App\Http\Controllers\Admin\QuestionQualityReviewController;
use App\Http\Controllers\Admin\SupportMessageController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FinanceCoinController;

// Welcome page
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Tek indirme linki — iOS/Android mağazaya, diğer cihazlarda seçim sayfası
Route::get('/download', AppDownloadController::class)->name('app.download');

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
    Route::post('duel-bot/bulk-active', [DuelBotController::class, 'bulkActive'])->name('duel-bot.bulk-active');
    Route::post('duel-bot/end-match', [DuelBotController::class, 'endMatch'])->name('duel-bot.end-match');
    Route::post('duel-bot/behavior', [DuelBotController::class, 'updateBehavior'])->name('duel-bot.behavior');
    Route::post('duel-bot/matchmaking', [DuelBotController::class, 'updateMatchmaking'])->name('duel-bot.matchmaking');
    Route::put('duel-bot/profile', [DuelBotController::class, 'updateProfile'])->name('duel-bot.profile');
    Route::put('duel-bot/avatar', [DuelBotController::class, 'updateAvatar'])->name('duel-bot.avatar');
    Route::put('duel-bot', [DuelBotController::class, 'update'])->name('duel-bot.update');

    // AI soru kalite review (permission: view/edit question quality)
    Route::get('question-quality-reviews', [QuestionQualityReviewController::class, 'index'])->name('question-quality-reviews.index');
    Route::get('question-quality-reviews/duplicates', [QuestionQualityReviewController::class, 'duplicates'])->name('question-quality-reviews.duplicates');
    Route::post('question-quality-reviews/duplicates/deactivate', [QuestionQualityReviewController::class, 'deactivateDuplicate'])->name('question-quality-reviews.duplicates.deactivate');
    Route::post('question-quality-reviews/duplicates/keep-oldest', [QuestionQualityReviewController::class, 'keepOldestDuplicates'])->name('question-quality-reviews.duplicates.keep-oldest');
    Route::post('question-quality-reviews/duplicates/delete', [QuestionQualityReviewController::class, 'deleteDuplicate'])->name('question-quality-reviews.duplicates.delete');
    Route::post('question-quality-reviews/duplicates/dismiss', [QuestionQualityReviewController::class, 'dismissDuplicateGroup'])->name('question-quality-reviews.duplicates.dismiss');
    Route::get('question-quality-reviews/poll', [QuestionQualityReviewController::class, 'poll'])->name('question-quality-reviews.poll');
    Route::post('question-quality-reviews/retry-failed-open', [QuestionQualityReviewController::class, 'retryFailedOpen'])->name('question-quality-reviews.retry-failed-open');
    Route::post('question-quality-reviews/bulk-apply-revision', [QuestionQualityReviewController::class, 'bulkApplyRevision'])->name('question-quality-reviews.bulk-apply-revision');
    Route::get('question-quality-reviews/{id}', [QuestionQualityReviewController::class, 'show'])->name('question-quality-reviews.show')->whereNumber('id');
    Route::post('question-quality-reviews/{id}/deactivate', [QuestionQualityReviewController::class, 'deactivateQuestion'])->name('question-quality-reviews.deactivate')->whereNumber('id');
    Route::post('question-quality-reviews/{id}/activate', [QuestionQualityReviewController::class, 'activateQuestion'])->name('question-quality-reviews.activate')->whereNumber('id');
    Route::post('question-quality-reviews/{id}/apply-revision', [QuestionQualityReviewController::class, 'applyRevision'])->name('question-quality-reviews.apply-revision')->whereNumber('id');

    // Destek kutusu (şimdilik sadece user #15 — muhammet kayacan)
    Route::get('support', [SupportMessageController::class, 'index'])->name('support.index');
    Route::get('support/unread-count', [SupportMessageController::class, 'unreadCount'])->name('support.unread-count');
    Route::get('support/{id}', [SupportMessageController::class, 'show'])->name('support.show')->whereNumber('id');
    Route::post('support/{id}/status', [SupportMessageController::class, 'updateStatus'])->name('support.status')->whereNumber('id');
    Route::post('support/{id}/reply', [SupportMessageController::class, 'reply'])->name('support.reply')->whereNumber('id');
    Route::delete('support/{id}', [SupportMessageController::class, 'destroy'])->name('support.destroy')->whereNumber('id');

    // Finans (şimdilik sadece user #15)
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('finance/settings', [FinanceController::class, 'settings'])->name('finance.settings');
    Route::post('finance/periods', [FinanceController::class, 'storePeriod'])->name('finance.periods.store');
    Route::put('finance/periods/{id}', [FinanceController::class, 'updatePeriod'])->name('finance.periods.update')->whereNumber('id');
    Route::delete('finance/periods/{id}', [FinanceController::class, 'destroyPeriod'])->name('finance.periods.destroy')->whereNumber('id');
    Route::post('finance/start-from-today', [FinanceController::class, 'startFromToday'])->name('finance.start-from-today');
    Route::post('finance/categories', [FinanceController::class, 'storeCategory'])->name('finance.categories.store');
    Route::post('finance/entries', [FinanceController::class, 'storeEntry'])->name('finance.entries.store');
    Route::put('finance/entries/{id}', [FinanceController::class, 'updateEntry'])->name('finance.entries.update')->whereNumber('id');
    Route::delete('finance/entries/{id}', [FinanceController::class, 'destroyEntry'])->name('finance.entries.destroy')->whereNumber('id');
    Route::get('finance/export', [FinanceController::class, 'export'])->name('finance.export');
    Route::post('finance/locks', [FinanceController::class, 'lockMonth'])->name('finance.locks.store');
    Route::delete('finance/locks', [FinanceController::class, 'unlockMonth'])->name('finance.locks.destroy');
    Route::get('finance/coin', [FinanceCoinController::class, 'index'])->name('finance.coin.index');

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







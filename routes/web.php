<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\QuestionController;
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
use App\Http\Controllers\Admin\SmsVitriniTestController;

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

    // Categories management
    Route::resource('categories', CategoryController::class);

    // Questions management
    Route::post('questions/{question}/toggle-check', [QuestionController::class, 'toggleCheck'])->name('questions.toggle-check');
    Route::resource('questions', QuestionController::class);

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

    // Reward Requests management
    Route::get('reward-requests', [\App\Http\Controllers\Admin\RewardRequestController::class, 'index'])->name('reward-requests.index');
    Route::post('reward-requests/{rewardRequest}/approve', [\App\Http\Controllers\Admin\RewardRequestController::class, 'approve'])->name('reward-requests.approve');
    Route::post('reward-requests/{rewardRequest}/reject', [\App\Http\Controllers\Admin\RewardRequestController::class, 'reject'])->name('reward-requests.reject');

    // Landing - üst grup
    Route::prefix('landing')->name('landing.')->group(function () {
        Route::resource('about', LandingAboutController::class)->parameters(['about' => 'about']);
        Route::resource('features', LandingFeatureController::class)->parameters(['features' => 'feature']);
        Route::resource('benefits', LandingBenefitController::class)->parameters(['benefits' => 'benefit']);
        Route::resource('testimonials', LandingTestimonialController::class)->parameters(['testimonials' => 'testimonial']);
        Route::resource('faqs', LandingFaqController::class)->parameters(['faqs' => 'faq']);
        Route::resource('news', LandingNewsController::class)->parameters(['news' => 'news']);
    });
});

Route::get('/api/documentation', function () {
    $documentation = 'default';
    $documentationTitle = 'BilBakalim API Documentation';
    $useAbsolutePath = true;
    $urlsToDocs = [
        'BilBakalim API' => asset('docs/api-docs.json')
    ];

    return view('l5-swagger::index', compact('documentation', 'documentationTitle', 'urlsToDocs', 'useAbsolutePath'));
})->name('l5-swagger.default.docs');

Route::get('/docs/api-docs.json', function () {
    return file_get_contents(storage_path('api-docs/api-docs.json'));
})->name('l5-swagger.default.docs.json');







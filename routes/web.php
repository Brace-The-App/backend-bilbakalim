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

// Welcome page
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Admin login redirect
Route::get('/private/lesley/admin', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
})->name('admin');

// Auth routes - Login & Logout
Route::get('/private/lesley/login', function () {
    return view('admin.auth.login');
})->name('login')->middleware('guest');

Route::post('/login', [App\Http\Controllers\API\Auth\AuthController::class, 'login_post'])->name('login.post');

Route::post('/logout', function () {
    auth()->logout();
    return redirect()->route('welcome');
})->name('logout');

// Admin panel routes
Route::prefix('private/lesley/admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Users management
    Route::resource('users', UserController::class);
    
    // Categories management
    Route::resource('categories', CategoryController::class);
    
    // Questions management
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
});

Route::get('private/lesley/jetwaldes/api/documentation', function () {
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







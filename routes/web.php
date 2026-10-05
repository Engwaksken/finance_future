<?php

use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Frontend\ArticleController;
use App\Http\Controllers\Frontend\EventController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\NewsletterController;
use App\Http\Controllers\Frontend\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/category/{slug}', [ArticleController::class, 'category'])->name('categories.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show');
Route::view('/about', 'frontend.about')->name('about');
Route::view('/contact', 'frontend.contact')->name('contact');
Route::view('/privacy', 'frontend.privacy')->name('privacy');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'store'])->middleware('throttle:10,1')->name('newsletter.subscribe');

Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');

    Route::resource('articles', AdminArticleController::class)->only(['index', 'edit', 'store', 'update', 'destroy'])->middleware('permission:articles.manage');
    Route::resource('categories', AdminCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:categories.manage');
    Route::resource('users', AdminUserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:users.manage');
    Route::resource('roles', AdminRoleController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:roles.manage');

    Route::get('/settings', [AdminSiteSettingController::class, 'index'])->middleware('permission:settings.manage')->name('settings.index');
    Route::put('/settings', [AdminSiteSettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::get('/settings/backup', [AdminSiteSettingController::class, 'backup'])->middleware('permission:backups.manage')->name('settings.backup');
});

require __DIR__.'/auth.php';

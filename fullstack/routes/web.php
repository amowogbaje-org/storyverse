<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth - guest only
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.register.show');
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.login.show');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::get('/verify-email', [AuthController::class, 'showVerify'])->name('auth.verify.show');
    Route::post('/verify-email', [AuthController::class, 'verifyOtp'])->name('auth.verify');
    Route::post('/verify-email/resend', [AuthController::class, 'resendOtp'])->name('auth.verify.resend');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('auth.forgot-password.show');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('auth.reset-password.show');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');

// Public browse/read
Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
Route::get('/stories/{slug}', [StoryController::class, 'show'])->name('stories.show');
Route::get('/stories/{slug}/episodes/{episodeNumber}', [EpisodeController::class, 'show'])
    ->whereNumber('episodeNumber')->name('episodes.show');

// External data contract for CraftProfessor - a fixed JSON endpoint for
// another system, not part of an app-wide API surface, so it stays a single
// explicit route rather than justifying a whole api.php.
Route::get('/api/stories/{slug}/json', [\App\Http\Controllers\Integrations\CraftProfessorExportController::class, 'show']);

// Payment gateway callbacks - external POSTs, CSRF-exempt (see bootstrap/app.php).
Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])->name('webhooks.handle');

// Signed-in reader actions
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/request-author', [ProfileController::class, 'requestAuthor'])->name('profile.request-author');

    Route::post('/stories/{slug}/like', [InteractionController::class, 'like'])->name('stories.like');
    Route::delete('/stories/{slug}/like', [InteractionController::class, 'unlike'])->name('stories.unlike');
    Route::post('/stories/{slug}/bookmark', [InteractionController::class, 'bookmark'])->name('stories.bookmark');
    Route::delete('/stories/{slug}/bookmark', [InteractionController::class, 'unbookmark'])->name('stories.unbookmark');
    Route::post('/stories/{slug}/episodes/{episodeNumber}/progress', [InteractionController::class, 'updateProgress'])
        ->whereNumber('episodeNumber')->name('episodes.progress');

    Route::post('/stories/{slug}/purchase', [PurchaseController::class, 'checkout'])->name('stories.purchase');
});

// Author studio - authors manage their own stories/episodes/pen names, admins can manage anyone's
Route::middleware(['auth', 'author'])->prefix('studio')->name('studio.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Studio\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pen-names', [\App\Http\Controllers\Studio\PenNameController::class, 'index'])->name('pen-names.index');
    Route::post('/pen-names', [\App\Http\Controllers\Studio\PenNameController::class, 'store'])->name('pen-names.store');
    Route::patch('/pen-names/{id}', [\App\Http\Controllers\Studio\PenNameController::class, 'update'])->name('pen-names.update');

    Route::get('/stories', [\App\Http\Controllers\Studio\StoryController::class, 'index'])->name('stories.index');
    Route::get('/stories/create', [\App\Http\Controllers\Studio\StoryController::class, 'create'])->name('stories.create');
    Route::post('/stories', [\App\Http\Controllers\Studio\StoryController::class, 'store'])->name('stories.store');
    Route::get('/stories/{id}/edit', [\App\Http\Controllers\Studio\StoryController::class, 'edit'])->name('stories.edit');
    Route::patch('/stories/{id}', [\App\Http\Controllers\Studio\StoryController::class, 'update'])->name('stories.update');
    Route::post('/stories/{id}/publish', [\App\Http\Controllers\Studio\StoryController::class, 'publish'])->name('stories.publish');
    Route::post('/stories/{id}/unpublish', [\App\Http\Controllers\Studio\StoryController::class, 'unpublish'])->name('stories.unpublish');
    Route::delete('/stories/{id}', [\App\Http\Controllers\Studio\StoryController::class, 'destroy'])->name('stories.destroy');

    Route::get('/stories/{storyId}/episodes', [\App\Http\Controllers\Studio\EpisodeController::class, 'index'])->name('stories.episodes.index');
    Route::get('/stories/{storyId}/episodes/create', [\App\Http\Controllers\Studio\EpisodeController::class, 'create'])->name('stories.episodes.create');
    Route::post('/stories/{storyId}/episodes', [\App\Http\Controllers\Studio\EpisodeController::class, 'store'])->name('stories.episodes.store');
    Route::get('/stories/{storyId}/episodes/{episodeId}/edit', [\App\Http\Controllers\Studio\EpisodeController::class, 'edit'])->name('stories.episodes.edit');
    Route::patch('/stories/{storyId}/episodes/{episodeId}', [\App\Http\Controllers\Studio\EpisodeController::class, 'update'])->name('stories.episodes.update');
    Route::post('/stories/{storyId}/episodes/{episodeId}/publish', [\App\Http\Controllers\Studio\EpisodeController::class, 'publish'])->name('stories.episodes.publish');
    Route::delete('/stories/{storyId}/episodes/{episodeId}', [\App\Http\Controllers\Studio\EpisodeController::class, 'destroy'])->name('stories.episodes.destroy');

    Route::post('/uploads/image', [\App\Http\Controllers\Studio\ImageUploadController::class, 'store'])->name('uploads.image');
});

// Platform admin - who gets to publish is an admin-only decision
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [\App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users/{id}/grant-author', [\App\Http\Controllers\Admin\UserManagementController::class, 'grantAuthor'])->name('users.grant-author');
    Route::post('/users/{id}/reject-author-request', [\App\Http\Controllers\Admin\UserManagementController::class, 'rejectAuthorRequest'])->name('users.reject-author-request');
    Route::post('/users/{id}/revoke-author', [\App\Http\Controllers\Admin\UserManagementController::class, 'revokeAuthor'])->name('users.revoke-author');
});

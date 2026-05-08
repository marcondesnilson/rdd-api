<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminPublicationController;
use App\Http\Controllers\AdminVerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\TimelinePostController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RecordAuthenticatedAccess;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/publications', [PublicationController::class, 'index']);
Route::get('/publications/home', [PublicationController::class, 'home']);
Route::get('/publications/{publicationRef}/comments', [PublicationController::class, 'comments']);
Route::get('/publications/{publicationRef}/{slug?}', [PublicationController::class, 'show']);
Route::post('/publications/{publication:slug}/views', [PublicationController::class, 'view']);

Route::middleware(['auth:sanctum', RecordAuthenticatedAccess::class])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/dashboard/metrics', [DashboardController::class, 'metrics']);
    Route::get('/me/publications', [PublicationController::class, 'myPublications']);
    Route::patch('/me', [AuthController::class, 'updateMe']);
    Route::post('/me/verification', [AuthController::class, 'requestVerification']);
    Route::post('/me/avatar', [AuthController::class, 'uploadAvatar']);
    Route::patch('/me/security', [AuthController::class, 'updateSecurity']);
    Route::post('/auth/mfa/verify', [AuthController::class, 'verifyMfa']);
    Route::get('/me/sessions', [AuthController::class, 'sessions']);
    Route::delete('/me/sessions', [AuthController::class, 'destroyOtherSessions']);
    Route::delete('/me/sessions/{tokenId}', [AuthController::class, 'destroySession']);
    Route::get('/me/activity', [AuthController::class, 'activity']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/publications', [PublicationController::class, 'store']);
    Route::post('/publications/files/upload', [PublicationController::class, 'uploadFile']);
    Route::post('/publications/{publicationRef}/comments', [PublicationController::class, 'storeComment']);
    Route::delete('/publications/{publicationRef}/comments/{commentId}', [PublicationController::class, 'destroyComment']);
    Route::post('/publications/{publicationRef}/likes', [PublicationController::class, 'like']);
    Route::delete('/publications/{publicationRef}/likes', [PublicationController::class, 'unlike']);
    Route::post('/publications/{publicationRef}/saves', [PublicationController::class, 'savePublication']);
    Route::delete('/publications/{publicationRef}/saves', [PublicationController::class, 'unsavePublication']);
    Route::post('/profiles/{author}/follow', [PublicationController::class, 'followAuthor']);
    Route::delete('/profiles/{author}/follow', [PublicationController::class, 'unfollowAuthor']);

    Route::get('/timeline/posts', [TimelinePostController::class, 'index']);
    Route::post('/timeline/posts', [TimelinePostController::class, 'store']);
    Route::get('/profiles/{user}/timeline-posts', [TimelinePostController::class, 'profileTimeline']);

    Route::middleware(EnsureAdmin::class)->prefix('/admin')->group(function (): void {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/users/{user}/sessions', [AdminUserController::class, 'sessions']);
        Route::get('/users/{user}/logs', [AdminUserController::class, 'logs']);
        Route::get('/users/{user}/audits', [AdminUserController::class, 'audits']);
        Route::get('/publications', [AdminPublicationController::class, 'index']);
        Route::patch('/publications/{publication:slug}/status', [AdminPublicationController::class, 'updateStatus']);
        Route::get('/verifications', [AdminVerificationController::class, 'index']);
        Route::patch('/verifications/{verification}/review', [AdminVerificationController::class, 'review']);
    });
});

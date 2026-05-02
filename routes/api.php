<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\RecordAuthenticatedAccess;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum', RecordAuthenticatedAccess::class])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateMe']);
    Route::post('/me/avatar', [AuthController::class, 'uploadAvatar']);
    Route::patch('/me/security', [AuthController::class, 'updateSecurity']);
    Route::post('/auth/mfa/verify', [AuthController::class, 'verifyMfa']);
    Route::get('/me/sessions', [AuthController::class, 'sessions']);
    Route::delete('/me/sessions', [AuthController::class, 'destroyOtherSessions']);
    Route::delete('/me/sessions/{tokenId}', [AuthController::class, 'destroySession']);
    Route::get('/me/activity', [AuthController::class, 'activity']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::get('/admin/users/{user}/sessions', [AdminUserController::class, 'sessions']);
    Route::get('/admin/users/{user}/logs', [AdminUserController::class, 'logs']);
    Route::get('/admin/users/{user}/audits', [AdminUserController::class, 'audits']);
});

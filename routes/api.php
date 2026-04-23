<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Account\EmailController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Security\ReauthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;

Route::get('/health', fn () => response()->json([
    'message' => 'success',
    'data' => [
        'status' => 'ok',
        'app' => config('app.name'),
    ],
]));

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auth/register/email', [RegisterController::class, 'store']);
Route::post('/auth/login/email', [LoginController::class, 'store'])->middleware('web');
Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend']);
Route::get('/auth/email/verify', [EmailVerificationController::class, 'verify'])->name('api.email.verify');
Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'store']);
Route::get('/auth/reset-password', [ResetPasswordController::class, 'show']);
Route::post('/auth/reset-password', [ResetPasswordController::class, 'store']);

Route::middleware(['web', 'auth:sanctum'])->group(function (): void {
    Route::post('/auth/logout', [LoginController::class, 'destroy']);
    Route::get('/auth/me', [LoginController::class, 'me']);

    Route::get('/account/profile', [ProfileController::class, 'show']);
    Route::put('/account/profile', [ProfileController::class, 'update']);
    Route::post('/account/email/change-request', [EmailController::class, 'store']);
    Route::put('/account/password', [PasswordController::class, 'update']);
    Route::get('/account/reauth', [ReauthController::class, 'show']);
    Route::post('/account/reauth', [ReauthController::class, 'store']);
});

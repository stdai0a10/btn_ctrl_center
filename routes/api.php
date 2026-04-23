<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisterController;

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
Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend']);
Route::get('/auth/email/verify', [EmailVerificationController::class, 'verify'])->name('api.email.verify');

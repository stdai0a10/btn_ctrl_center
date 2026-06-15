<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Account\EmailController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\ProviderBindingController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HouseController;
use App\Http\Controllers\HouseInvitationController;
use App\Http\Controllers\HouseMemberController;
use App\Http\Controllers\HouseJoinRequestController;
use App\Http\Controllers\Security\ReauthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LineAuthController;
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
Route::post('/auth/login/email', [LoginController::class, 'store']);
Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend']);
Route::get('/auth/email/verify', [EmailVerificationController::class, 'verify'])->name('api.email.verify');
Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'store']);
Route::get('/auth/reset-password', [ResetPasswordController::class, 'show']);
Route::post('/auth/reset-password', [ResetPasswordController::class, 'store']);
Route::get('/auth/line/redirect', [LineAuthController::class, 'redirect'])->middleware('web')->name('auth.line.redirect');
Route::get('/auth/line/callback', [LineAuthController::class, 'callback'])->middleware('web')->name('auth.line.callback');
Route::post('/auth/line/liff', [LineAuthController::class, 'liff']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [LoginController::class, 'destroy']);
    Route::get('/auth/me', [LoginController::class, 'me']);

    Route::get('/account/profile', [ProfileController::class, 'show']);
    Route::put('/account/profile', [ProfileController::class, 'update']);
    Route::post('/account/email/change-request', [EmailController::class, 'store']);
    Route::put('/account/password', [PasswordController::class, 'update']);
    Route::get('/account/reauth', [ReauthController::class, 'show']);
    Route::post('/account/reauth', [ReauthController::class, 'store']);
    Route::get('/account/providers/line/bind', [ProviderBindingController::class, 'lineRedirect'])->name('account.providers.line.bind');
    Route::post('/account/providers/line/bind', [ProviderBindingController::class, 'lineRedirect']);
    Route::get('/account/providers/line/callback', [ProviderBindingController::class, 'lineCallback'])->name('account.providers.line.callback');
    Route::delete('/account/providers/line', [ProviderBindingController::class, 'destroyLine']);

    Route::apiResource('houses', HouseController::class);
    Route::get('/houses/{house}/members', [HouseMemberController::class, 'index']);
    Route::delete('/houses/{house}/members/{user:public_id}', [HouseMemberController::class, 'destroy']);
    Route::post('/houses/{house}/leave', [HouseMemberController::class, 'leave']);
    Route::patch('/houses/{house}/members/{user:public_id}/role', [HouseMemberController::class, 'updateRole']);

    Route::get('/house-invitations', [HouseInvitationController::class, 'index']);
    Route::post('/houses/{house}/invitations', [HouseInvitationController::class, 'store']);
    Route::post('/house-invitations/{invitation}/accept', [HouseInvitationController::class, 'accept']);
    Route::post('/house-invitations/{invitation}/ignore', [HouseInvitationController::class, 'ignore']);
    Route::post('/house-invitations/{invitation}/cancel', [HouseInvitationController::class, 'cancel']);

    Route::get('/house-join-requests', [HouseJoinRequestController::class, 'index']);
    Route::post('/houses/{house}/join-requests', [HouseJoinRequestController::class, 'store']);
    Route::post('/house-join-requests/{joinRequest}/accept', [HouseJoinRequestController::class, 'accept']);
    Route::post('/house-join-requests/{joinRequest}/ignore', [HouseJoinRequestController::class, 'ignore']);
    Route::post('/house-join-requests/{joinRequest}/cancel', [HouseJoinRequestController::class, 'cancel']);

    Route::get('/houses/{house}/devices', [DeviceController::class, 'index']);
    Route::post('/houses/{house}/devices', [DeviceController::class, 'store']);
    Route::patch('/houses/{house}/devices/{device}', [DeviceController::class, 'update']);
    Route::delete('/houses/{house}/devices/{device}', [DeviceController::class, 'destroy']);
    Route::post('/houses/{house}/devices/{device}/lock', [DeviceController::class, 'lock']);
    Route::post('/houses/{house}/devices/{device}/unlock', [DeviceController::class, 'unlock']);
});

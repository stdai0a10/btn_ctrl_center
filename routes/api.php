<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Account\EmailController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\ProviderBindingController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomInvitationController;
use App\Http\Controllers\RoomMemberController;
use App\Http\Controllers\RoomJoinRequestController;
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

    Route::apiResource('rooms', RoomController::class);
    Route::get('/rooms/{room}/members', [RoomMemberController::class, 'index']);
    Route::delete('/rooms/{room}/members/{user:public_id}', [RoomMemberController::class, 'destroy']);
    Route::post('/rooms/{room}/leave', [RoomMemberController::class, 'leave']);
    Route::patch('/rooms/{room}/members/{user:public_id}/role', [RoomMemberController::class, 'updateRole']);

    Route::get('/room-invitations', [RoomInvitationController::class, 'index']);
    Route::post('/rooms/{room}/invitations', [RoomInvitationController::class, 'store']);
    Route::post('/room-invitations/{invitation}/accept', [RoomInvitationController::class, 'accept']);
    Route::post('/room-invitations/{invitation}/ignore', [RoomInvitationController::class, 'ignore']);
    Route::post('/room-invitations/{invitation}/cancel', [RoomInvitationController::class, 'cancel']);

    Route::get('/room-join-requests', [RoomJoinRequestController::class, 'index']);
    Route::post('/rooms/{room}/join-requests', [RoomJoinRequestController::class, 'store']);
    Route::post('/room-join-requests/{joinRequest}/accept', [RoomJoinRequestController::class, 'accept']);
    Route::post('/room-join-requests/{joinRequest}/ignore', [RoomJoinRequestController::class, 'ignore']);
    Route::post('/room-join-requests/{joinRequest}/cancel', [RoomJoinRequestController::class, 'cancel']);

    Route::get('/rooms/{room}/devices', [DeviceController::class, 'index']);
    Route::post('/rooms/{room}/devices', [DeviceController::class, 'store']);
    Route::patch('/rooms/{room}/devices/{device}', [DeviceController::class, 'update']);
    Route::delete('/rooms/{room}/devices/{device}', [DeviceController::class, 'destroy']);
    Route::post('/rooms/{room}/devices/{device}/lock', [DeviceController::class, 'lock']);
    Route::post('/rooms/{room}/devices/{device}/unlock', [DeviceController::class, 'unlock']);
    Route::post('/rooms/{room}/devices/{device}/enable', [DeviceController::class, 'enable']);
    Route::post('/rooms/{room}/devices/{device}/disable', [DeviceController::class, 'disable']);
});

<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Manage\AuthController as ManageAuthController;

Route::get('/', function () {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
});

Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('/manage/login', fn () => Inertia::render('Manage/Login'))->name('manage.login');
Route::get('/verify-email/result', fn () => Inertia::render('Auth/VerifyEmailResult'))->name('email.verify.result');
Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
Route::get('/reset-password', fn () => Inertia::render('Auth/ResetPassword'))->name('password.reset');

Route::middleware('auth')->group(function (): void {
    Route::get('/account/profile', fn () => Inertia::render('Account/Profile'))->name('account.profile');
    Route::get('/account/email', fn () => Inertia::render('Account/Email'))->name('account.email');
    Route::get('/account/security', fn () => Inertia::render('Account/Security'))->name('account.security');
    Route::get('/account/providers', fn () => Inertia::render('Account/Providers'))->name('account.providers');
    Route::get('/reauth', fn () => Inertia::render('Account/Reauth'))->name('account.reauth');
    Route::get('/rooms', fn () => Inertia::render('Rooms/Index'))->name('rooms.index');
    Route::get('/rooms/{room}', fn (string $room) => Inertia::render('Rooms/Show', [
        'roomPublicId' => $room,
    ]))->name('rooms.show');
    Route::get('/room-invitations', fn () => Inertia::render('Rooms/Invitations'))->name('room-invitations.index');
});

Route::prefix('manage')->name('manage.')->group(function (): void {
    Route::prefix('api')->name('api.')->group(function (): void {
        Route::post('/login', [ManageAuthController::class, 'store'])->name('login');

        Route::middleware(['auth', 'manage.authenticated', 'permission:manage.access'])->group(function (): void {
            Route::post('/logout', [ManageAuthController::class, 'destroy'])->name('logout');
        });
    });
});

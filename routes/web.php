<?php

use App\Http\Controllers\Account\ProviderBindingController;
use App\Http\Controllers\Auth\LineAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
});

Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('/verify-email/result', fn () => Inertia::render('Auth/VerifyEmailResult'))->name('email.verify.result');
Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
Route::get('/reset-password', fn () => Inertia::render('Auth/ResetPassword'))->name('password.reset');

Route::get('/api/auth/line/redirect', [LineAuthController::class, 'redirect'])->name('auth.line.redirect');
Route::get('/api/auth/line/callback', [LineAuthController::class, 'callback'])->name('auth.line.callback');

Route::middleware('auth')->group(function (): void {
    Route::get('/account/profile', fn () => Inertia::render('Account/Profile'))->name('account.profile');
    Route::get('/account/email', fn () => Inertia::render('Account/Email'))->name('account.email');
    Route::get('/account/security', fn () => Inertia::render('Account/Security'))->name('account.security');
    Route::get('/account/providers', fn () => Inertia::render('Account/Providers'))->name('account.providers');
    Route::get('/reauth', fn () => Inertia::render('Account/Reauth'))->name('account.reauth');
    Route::get('/api/account/providers/line/bind', [ProviderBindingController::class, 'lineRedirect'])->name('account.providers.line.bind');
    Route::post('/api/account/providers/line/bind', [ProviderBindingController::class, 'lineRedirect']);
    Route::get('/api/account/providers/line/callback', [ProviderBindingController::class, 'lineCallback'])->name('account.providers.line.callback');
    Route::get('/rooms', fn () => Inertia::render('Rooms/Index'))->name('rooms.index');
    Route::get('/buttons', fn () => Inertia::render('Buttons/Index'))->name('buttons.index');
    Route::get('/rooms/{room}', fn (string $room) => Inertia::render('Rooms/Show', [
        'roomPublicId' => $room,
    ]))->name('rooms.show');
    Route::get('/room-invitations', fn () => Inertia::render('Rooms/Invitations'))->name('room-invitations.index');
});

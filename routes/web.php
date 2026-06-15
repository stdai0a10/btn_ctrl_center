<?php

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

Route::middleware('auth')->group(function (): void {
    Route::get('/account/profile', fn () => Inertia::render('Account/Profile'))->name('account.profile');
    Route::get('/account/email', fn () => Inertia::render('Account/Email'))->name('account.email');
    Route::get('/account/security', fn () => Inertia::render('Account/Security'))->name('account.security');
    Route::get('/account/providers', fn () => Inertia::render('Account/Providers'))->name('account.providers');
    Route::get('/reauth', fn () => Inertia::render('Account/Reauth'))->name('account.reauth');
    Route::get('/houses', fn () => Inertia::render('Houses/Index'))->name('houses.index');
    Route::get('/houses/{house}', fn (string $house) => Inertia::render('Houses/Show', [
        'housePublicId' => $house,
    ]))->name('houses.show');
    Route::get('/house-invitations', fn () => Inertia::render('Houses/Invitations'))->name('house-invitations.index');
});

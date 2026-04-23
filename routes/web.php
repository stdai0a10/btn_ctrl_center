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

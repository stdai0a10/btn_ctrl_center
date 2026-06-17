<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Manage\AuthController as ManageAuthController;
use App\Http\Controllers\Manage\DashboardController as ManageDashboardController;
use App\Http\Controllers\Manage\UserController as ManageUserController;

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
    Route::middleware(['auth', 'manage.authenticated', 'permission:manage.access'])->group(function (): void {
        Route::get('/', fn () => Inertia::render('Manage/Index'))->name('index');
        Route::get('/users', fn () => Inertia::render('Manage/Users/Index'))->name('users.index');
        Route::get('/users/{user_public_id}', fn (string $userPublicId) => Inertia::render('Manage/Users/Show', [
            'userPublicId' => $userPublicId,
        ]))->name('users.show');
    });

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::post('/login', [ManageAuthController::class, 'store'])->name('login');

        Route::middleware(['auth', 'manage.authenticated', 'permission:manage.access'])->group(function (): void {
            Route::post('/logout', [ManageAuthController::class, 'destroy'])->name('logout');
            Route::get('/me', [ManageDashboardController::class, 'me'])
                ->middleware('permission:manage.access')
                ->name('me');
            Route::get('/dashboard', [ManageDashboardController::class, 'dashboard'])
                ->middleware('permission:manage.dashboard.view')
                ->name('dashboard');
            Route::get('/users', [ManageUserController::class, 'index'])
                ->middleware('permission:manage.users.view')
                ->name('users.index');
            Route::get('/users/{user_public_id}', [ManageUserController::class, 'show'])
                ->middleware('permission:manage.users.detail')
                ->name('users.show');
            Route::get('/users/{user_public_id}/rooms', [ManageUserController::class, 'rooms'])
                ->middleware('permission:manage.users.detail')
                ->name('users.rooms');
        });
    });
});

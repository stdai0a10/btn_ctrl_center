<?php

use App\Http\Controllers\Manage\AuditController as ManageAuditController;
use App\Http\Controllers\Manage\AuthController as ManageAuthController;
use App\Http\Controllers\Manage\DashboardController as ManageDashboardController;
use App\Http\Controllers\Manage\DeviceController as ManageDeviceController;
use App\Http\Controllers\Manage\ProductController as ManageProductController;
use App\Http\Controllers\Manage\RoomController as ManageRoomController;
use App\Http\Controllers\Manage\ServiceManagerController as ManageServiceManagerController;
use App\Http\Controllers\Manage\UserController as ManageUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
        Route::get('/', fn () => Inertia::render('Manage/Index'))
            ->middleware('permission:manage.dashboard.view')
            ->name('index');
        Route::get('/users', fn () => Inertia::render('Manage/Users/Index'))
            ->middleware('permission:manage.users.view')
            ->name('users.index');
        Route::get('/users/{user_public_id}', fn (string $userPublicId) => Inertia::render('Manage/Users/Show', [
            'userPublicId' => $userPublicId,
        ]))
            ->middleware('permission:manage.users.detail')
            ->name('users.show');
        Route::get('/rooms', fn () => Inertia::render('Manage/Rooms/Index'))
            ->middleware('permission:manage.rooms.view')
            ->name('rooms.index');
        Route::get('/rooms/{room_public_id}', fn (string $roomPublicId) => Inertia::render('Manage/Rooms/Show', [
            'roomPublicId' => $roomPublicId,
        ]))
            ->middleware('permission:manage.rooms.detail')
            ->name('rooms.show');
        Route::get('/devices', fn () => Inertia::render('Manage/Devices/Index'))
            ->middleware('permission:manage.devices.view')
            ->name('devices.index');
        Route::get('/devices/create', fn () => Inertia::render('Manage/Devices/Create'))
            ->middleware('permission:manage.devices.create')
            ->name('devices.create');
        Route::get('/devices/{serial_number}', fn (string $serialNumber) => Inertia::render('Manage/Devices/Show', [
            'serialNumber' => $serialNumber,
        ]))
            ->middleware('permission:manage.devices.detail')
            ->name('devices.show');
        Route::get('/products', fn () => Inertia::render('Manage/Products/Index'))
            ->middleware('permission:manage.products.view')
            ->name('products.index');
        Route::get('/products/create', fn () => Inertia::render('Manage/Products/Create'))
            ->middleware('permission:manage.products.create')
            ->name('products.create');
        Route::get('/products/{product_public_id}', fn (string $productPublicId) => Inertia::render('Manage/Products/Show', [
            'productPublicId' => $productPublicId,
        ]))
            ->middleware('permission:manage.products.detail')
            ->name('products.show');
        Route::get('/service-managers', fn () => Inertia::render('Manage/ServiceManagers/Index'))
            ->middleware('permission:manage.service_managers.view')
            ->name('service-managers.index');
        Route::get('/service-managers/{user_public_id}', fn (string $userPublicId) => Inertia::render('Manage/ServiceManagers/Show', [
            'userPublicId' => $userPublicId,
        ]))
            ->middleware('permission:manage.service_managers.detail')
            ->name('service-managers.show');
        Route::middleware('permission:audit.access')->prefix('audit')->name('audit.')->group(function (): void {
            Route::get('/', fn () => Inertia::render('Manage/Audit/Index'))->name('index');
            Route::get('/login-failures', fn () => Inertia::render('Manage/Audit/LoginFailures'))
                ->middleware('permission:audit.login_failures.view')
                ->name('login-failures');
            Route::get('/manage-actions', fn () => Inertia::render('Manage/Audit/ManageActions'))
                ->middleware('permission:audit.manage_actions.view')
                ->name('manage-actions');
        });
    });

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::post('/login', [ManageAuthController::class, 'store'])->name('login');

        Route::middleware(['auth', 'manage.authenticated', 'permission:manage.access', 'manage.audit'])->group(function (): void {
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
            Route::get('/rooms', [ManageRoomController::class, 'index'])
                ->middleware('permission:manage.rooms.view')
                ->name('rooms.index');
            Route::get('/rooms/{room_public_id}', [ManageRoomController::class, 'show'])
                ->middleware('permission:manage.rooms.detail')
                ->name('rooms.show');
            Route::get('/rooms/{room_public_id}/users', [ManageRoomController::class, 'users'])
                ->middleware('permission:manage.rooms.detail')
                ->name('rooms.users');
            Route::get('/rooms/{room_public_id}/devices', [ManageDeviceController::class, 'roomDevices'])
                ->middleware(['permission:manage.rooms.detail', 'permission:manage.devices.view'])
                ->name('rooms.devices');
            Route::get('/devices', [ManageDeviceController::class, 'index'])
                ->middleware('permission:manage.devices.view')
                ->name('devices.index');
            Route::post('/devices', [ManageDeviceController::class, 'store'])
                ->middleware('permission:manage.devices.create')
                ->name('devices.store');
            Route::get('/devices/{serial_number}', [ManageDeviceController::class, 'show'])
                ->middleware('permission:manage.devices.detail')
                ->name('devices.show');
            Route::get('/products', [ManageProductController::class, 'index'])
                ->middleware('permission:manage.products.view')
                ->name('products.index');
            Route::post('/products', [ManageProductController::class, 'store'])
                ->middleware('permission:manage.products.create')
                ->name('products.store');
            Route::get('/products/{product_public_id}', [ManageProductController::class, 'show'])
                ->middleware('permission:manage.products.detail')
                ->name('products.show');
            Route::patch('/products/{product_public_id}', [ManageProductController::class, 'update'])
                ->middleware('permission:manage.products.update')
                ->name('products.update');
            Route::post('/products/{product_public_id}/functions', [ManageProductController::class, 'storeFunction'])
                ->middleware('permission:manage.product_functions.create')
                ->name('products.functions.store');
            Route::patch('/product-functions/{code}', [ManageProductController::class, 'updateFunction'])
                ->middleware('permission:manage.product_functions.update')
                ->name('product-functions.update');
            Route::get('/service-managers', [ManageServiceManagerController::class, 'index'])
                ->middleware('permission:manage.service_managers.view')
                ->name('service-managers.index');
            Route::post('/service-managers/grant-many', [ManageServiceManagerController::class, 'grantMany'])
                ->middleware('permission:manage.service_managers.grant')
                ->name('service-managers.grant-many');
            Route::post('/service-managers/revoke-many', [ManageServiceManagerController::class, 'revokeMany'])
                ->middleware('permission:manage.service_managers.revoke')
                ->name('service-managers.revoke-many');
            Route::get('/service-managers/{user_public_id}', [ManageServiceManagerController::class, 'show'])
                ->middleware('permission:manage.service_managers.detail')
                ->name('service-managers.show');
            Route::post('/service-managers/{user_public_id}/grant', [ManageServiceManagerController::class, 'grant'])
                ->middleware('permission:manage.service_managers.grant')
                ->name('service-managers.grant');
            Route::post('/service-managers/{user_public_id}/revoke', [ManageServiceManagerController::class, 'revoke'])
                ->middleware('permission:manage.service_managers.revoke')
                ->name('service-managers.revoke');
            Route::get('/audit/login-failures', [ManageAuditController::class, 'loginFailures'])
                ->middleware(['permission:audit.access', 'permission:audit.login_failures.view'])
                ->name('audit.login-failures');
            Route::get('/audit/manage-actions', [ManageAuditController::class, 'actions'])
                ->middleware(['permission:audit.access', 'permission:audit.manage_actions.view'])
                ->name('audit.manage-actions');
        });
    });
});

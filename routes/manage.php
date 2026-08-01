<?php

use App\Http\Controllers\Manage\AuditController;
use App\Http\Controllers\Manage\AuthController;
use App\Http\Controllers\Manage\ButtonJobController;
use App\Http\Controllers\Manage\DashboardController;
use App\Http\Controllers\Manage\DeviceController;
use App\Http\Controllers\Manage\DeviceRuntimeController;
use App\Http\Controllers\Manage\ProductController;
use App\Http\Controllers\Manage\RoomController;
use App\Http\Controllers\Manage\ServiceManagerController;
use App\Http\Controllers\Manage\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', fn () => Inertia::render('Manage/Login'))->name('login');

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
    Route::get('/device-runtime', fn () => Inertia::render('Manage/DeviceRuntime/Index'))
        ->middleware('permission:manage.device_runtime.view')
        ->name('device-runtime.index');
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
    Route::post('/login', [AuthController::class, 'store'])->name('login');

    Route::middleware(['auth', 'manage.authenticated', 'permission:manage.access', 'manage.audit'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/me', [DashboardController::class, 'me'])
            ->middleware('permission:manage.access')
            ->name('me');
        Route::get('/dashboard', [DashboardController::class, 'dashboard'])
            ->middleware('permission:manage.dashboard.view')
            ->name('dashboard');
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:manage.users.view')
            ->name('users.index');
        Route::get('/users/{user_public_id}', [UserController::class, 'show'])
            ->middleware('permission:manage.users.detail')
            ->name('users.show');
        Route::get('/users/{user_public_id}/rooms', [UserController::class, 'rooms'])
            ->middleware('permission:manage.users.detail')
            ->name('users.rooms');
        Route::get('/rooms', [RoomController::class, 'index'])
            ->middleware('permission:manage.rooms.view')
            ->name('rooms.index');
        Route::get('/rooms/{room_public_id}', [RoomController::class, 'show'])
            ->middleware('permission:manage.rooms.detail')
            ->name('rooms.show');
        Route::get('/rooms/{room_public_id}/users', [RoomController::class, 'users'])
            ->middleware('permission:manage.rooms.detail')
            ->name('rooms.users');
        Route::get('/rooms/{room_public_id}/devices', [DeviceController::class, 'roomDevices'])
            ->middleware(['permission:manage.rooms.detail', 'permission:manage.devices.view'])
            ->name('rooms.devices');
        Route::get('/devices', [DeviceController::class, 'index'])
            ->middleware('permission:manage.devices.view')
            ->name('devices.index');
        Route::post('/devices', [DeviceController::class, 'store'])
            ->middleware('permission:manage.devices.create')
            ->name('devices.store');
        Route::get('/devices/{serial_number}', [DeviceController::class, 'show'])
            ->middleware('permission:manage.devices.detail')
            ->name('devices.show');
        Route::get('/device-runtime', [DeviceRuntimeController::class, 'index'])
            ->middleware('permission:manage.device_runtime.view')
            ->name('device-runtime.index');
        Route::post('/device-runtime/devices/{serial_number}/disable', [DeviceRuntimeController::class, 'disable'])
            ->middleware('permission:manage.device_runtime.manage')
            ->name('device-runtime.disable');
        Route::post('/device-runtime/devices/{serial_number}/enable', [DeviceRuntimeController::class, 'enable'])
            ->middleware('permission:manage.device_runtime.manage')
            ->name('device-runtime.enable');
        Route::post('/device-runtime/devices/{serial_number}/revoke-tokens', [DeviceRuntimeController::class, 'revokeTokens'])
            ->middleware('permission:manage.device_runtime.manage')
            ->name('device-runtime.revoke-tokens');
        Route::get('/button-jobs', [ButtonJobController::class, 'index'])
            ->middleware('permission:manage.button_jobs.view')
            ->name('button-jobs.index');
        Route::post('/button-jobs/{button_action_job_public_id}/cancel', [ButtonJobController::class, 'cancel'])
            ->middleware('permission:manage.button_jobs.cancel')
            ->name('button-jobs.cancel');
        Route::get('/products', [ProductController::class, 'index'])
            ->middleware('permission:manage.products.view')
            ->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('permission:manage.products.create')
            ->name('products.store');
        Route::get('/products/{product_public_id}', [ProductController::class, 'show'])
            ->middleware('permission:manage.products.detail')
            ->name('products.show');
        Route::patch('/products/{product_public_id}', [ProductController::class, 'update'])
            ->middleware('permission:manage.products.update')
            ->name('products.update');
        Route::post('/products/{product_public_id}/lock', [ProductController::class, 'lock'])
            ->middleware('permission:manage.products.lock')
            ->name('products.lock');
        Route::post('/products/{product_public_id}/functions', [ProductController::class, 'storeFunction'])
            ->middleware('permission:manage.product_functions.create')
            ->name('products.functions.store');
        Route::patch('/product-functions/{code}', [ProductController::class, 'updateFunction'])
            ->middleware('permission:manage.product_functions.update')
            ->name('product-functions.update');
        Route::delete('/product-functions/{code}', [ProductController::class, 'destroyFunction'])
            ->middleware('permission:manage.product_functions.delete')
            ->name('product-functions.destroy');
        Route::get('/service-managers', [ServiceManagerController::class, 'index'])
            ->middleware('permission:manage.service_managers.view')
            ->name('service-managers.index');
        Route::post('/service-managers/grant-many', [ServiceManagerController::class, 'grantMany'])
            ->middleware('permission:manage.service_managers.grant')
            ->name('service-managers.grant-many');
        Route::post('/service-managers/revoke-many', [ServiceManagerController::class, 'revokeMany'])
            ->middleware('permission:manage.service_managers.revoke')
            ->name('service-managers.revoke-many');
        Route::get('/service-managers/{user_public_id}', [ServiceManagerController::class, 'show'])
            ->middleware('permission:manage.service_managers.detail')
            ->name('service-managers.show');
        Route::post('/service-managers/{user_public_id}/grant', [ServiceManagerController::class, 'grant'])
            ->middleware('permission:manage.service_managers.grant')
            ->name('service-managers.grant');
        Route::post('/service-managers/{user_public_id}/revoke', [ServiceManagerController::class, 'revoke'])
            ->middleware('permission:manage.service_managers.revoke')
            ->name('service-managers.revoke');
        Route::get('/audit/login-failures', [AuditController::class, 'loginFailures'])
            ->middleware(['permission:audit.access', 'permission:audit.login_failures.view'])
            ->name('audit.login-failures');
        Route::get('/audit/manage-actions', [AuditController::class, 'actions'])
            ->middleware(['permission:audit.access', 'permission:audit.manage_actions.view'])
            ->name('audit.manage-actions');
    });
});

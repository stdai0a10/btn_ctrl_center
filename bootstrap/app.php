<?php

use App\Console\Commands\CleanupButtonRuntime;
use App\Console\Commands\CleanupExpiredAuthArtifacts;
use App\Console\Commands\SystemAdmin\AddSystemAdmin;
use App\Console\Commands\SystemAdmin\ListSystemAdmins;
use App\Console\Commands\SystemAdmin\RemoveSystemAdmin;
use App\Http\Middleware\EnsureManageAuthenticated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogManageAction;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('device/api')
                ->group(base_path('routes/device.php'));

            Route::middleware('web')
                ->prefix('manage')
                ->name('manage.')
                ->group(base_path('routes/manage.php'));
        },
    )
    ->withCommands([
        CleanupButtonRuntime::class,
        CleanupExpiredAuthArtifacts::class,
        AddSystemAdmin::class,
        ListSystemAdmins::class,
        RemoveSystemAdmin::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('manage', 'manage/*')
                ? route('manage.login')
                : route('login')
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->statefulApi();

        $middleware->alias([
            'manage.authenticated' => EnsureManageAuthenticated::class,
            'manage.audit' => LogManageAction::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

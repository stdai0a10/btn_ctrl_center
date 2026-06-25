<?php

namespace App\Support;

final class ManagementRbac
{
    public const SERVICE_MANAGER_ROLE = 'service_manager';

    public const SYSTEM_ADMIN_ROLE = 'system_admin';

    public const SERVICE_MANAGER_PERMISSIONS = [
        'manage.access',
        'manage.dashboard.view',
        'manage.users.view',
        'manage.users.detail',
        'manage.rooms.view',
        'manage.rooms.detail',
        'manage.devices.view',
        'manage.devices.detail',
        'manage.products.view',
        'manage.products.detail',
    ];

    public const SYSTEM_ADMIN_PERMISSIONS = [
        ...self::SERVICE_MANAGER_PERMISSIONS,
        'manage.service_managers.view',
        'manage.service_managers.detail',
        'manage.service_managers.grant',
        'manage.service_managers.revoke',
        'manage.devices.create',
        'manage.products.create',
        'manage.products.update',
        'manage.product_functions.create',
        'manage.product_functions.update',
        'audit.access',
        'audit.login_failures.view',
        'audit.manage_actions.view',
    ];

    private function __construct() {}
}

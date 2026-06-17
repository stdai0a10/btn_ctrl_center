<?php

namespace Database\Seeders;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ManagementRbacSeeder extends Seeder
{
    /**
     * Seed the management roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'manage.access',
            'manage.dashboard.view',
            'manage.users.view',
            'manage.users.detail',
            'manage.rooms.view',
            'manage.rooms.detail',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]),
        ]);

        foreach (['service_manager', 'system_admin'] as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($permissions->values());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

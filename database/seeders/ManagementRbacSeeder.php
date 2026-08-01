<?php

namespace Database\Seeders;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Support\ManagementRbac;
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

        $permissions = collect(ManagementRbac::SYSTEM_ADMIN_PERMISSIONS)
            ->mapWithKeys(fn (string $name): array => [
                $name => Permission::query()->firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]),
            ]);

        $serviceManager = Role::query()->firstOrCreate([
            'name' => ManagementRbac::SERVICE_MANAGER_ROLE,
            'guard_name' => 'web',
        ]);
        $serviceManager->syncPermissions(
            $permissions->only(ManagementRbac::SERVICE_MANAGER_PERMISSIONS)->values()
        );

        $systemAdmin = Role::query()->firstOrCreate([
            'name' => ManagementRbac::SYSTEM_ADMIN_ROLE,
            'guard_name' => 'web',
        ]);
        $systemAdmin->syncPermissions($permissions->values());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

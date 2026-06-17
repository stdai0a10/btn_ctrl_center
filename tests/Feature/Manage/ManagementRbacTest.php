<?php

namespace Tests\Feature\Manage;

use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_roles_receive_their_expected_permissions(): void
    {
        $this->seed(ManagementRbacSeeder::class);

        $serviceManager = User::factory()->create();
        $serviceManager->assignRole(ManagementRbac::SERVICE_MANAGER_ROLE);

        $systemAdmin = User::factory()->create();
        $systemAdmin->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $this->assertTrue($serviceManager->can('manage.access'));
        $this->assertFalse($serviceManager->can('manage.service_managers.view'));
        $this->assertFalse($serviceManager->can('audit.access'));

        foreach (ManagementRbac::SYSTEM_ADMIN_PERMISSIONS as $permission) {
            $this->assertTrue($systemAdmin->can($permission), $permission);
        }
    }

    public function test_user_can_hold_both_management_roles(): void
    {
        $this->seed(ManagementRbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole([
            ManagementRbac::SERVICE_MANAGER_ROLE,
            ManagementRbac::SYSTEM_ADMIN_ROLE,
        ]);

        $this->assertTrue($user->hasAllRoles([
            ManagementRbac::SERVICE_MANAGER_ROLE,
            ManagementRbac::SYSTEM_ADMIN_ROLE,
        ]));
        $this->assertTrue($user->can('manage.access'));
        $this->assertTrue($user->can('audit.access'));
    }

    public function test_manage_access_permission_allows_existing_management_flow_without_role_name_check(): void
    {
        $this->seed(ManagementRbacSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('manage.access');

        $response = $this->actingAs($user)
            ->withSession([
                'manage_authenticated_at' => now()->toISOString(),
                'manage_authenticated_user_id' => $user->id,
            ])
            ->get('/manage');

        $response->assertOk();
    }
}

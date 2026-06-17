<?php

namespace Tests\Feature\Manage;

use App\Models\Auth\UserEmail;
use App\Models\ManageActionLog;
use App\Models\ManageLoginLog;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdminIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
    }

    public function test_system_admin_can_login_and_access_existing_and_new_management_features(): void
    {
        $admin = $this->userWithEmail('system-admin@example.com');
        $admin->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $this->postJson('/manage/api/login', [
            'email' => 'system-admin@example.com',
            'password' => 'password-password',
        ])->assertOk();

        foreach ([
            '/manage',
            '/manage/users',
            '/manage/rooms',
            '/manage/service-managers',
            '/manage/audit',
            '/manage/audit/login-failures',
            '/manage/audit/manage-actions',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_service_manager_cannot_manage_roles_or_view_audit_but_dual_role_user_can(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(ManagementRbac::SERVICE_MANAGER_ROLE);

        $this->actingAs($manager)
            ->withSession($this->manageSession($manager))
            ->get('/manage/users')
            ->assertOk();
        $this->actingAs($manager)
            ->withSession($this->manageSession($manager))
            ->get('/manage/service-managers')
            ->assertForbidden();
        $this->actingAs($manager)
            ->withSession($this->manageSession($manager))
            ->get('/manage/audit')
            ->assertForbidden();

        $manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $this->actingAs($manager)
            ->withSession($this->manageSession($manager))
            ->get('/manage/service-managers')
            ->assertOk();
        $this->actingAs($manager)
            ->withSession($this->manageSession($manager))
            ->get('/manage/audit')
            ->assertOk();
    }

    public function test_management_apis_do_not_expose_sensitive_credentials(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $target = $this->userWithEmail('target@example.com');

        ManageLoginLog::query()->create([
            'user_id' => $target->id,
            'email' => 'target@example.com',
            'success' => false,
            'failure_reason' => 'invalid_credentials_or_permission',
        ]);
        ManageActionLog::query()->create([
            'actor_type' => 'manage_user',
            'actor_user_id' => $admin->id,
            'action' => 'users.detail.view',
            'target_type' => 'user',
            'target_id' => $target->id,
            'target_public_id' => $target->public_id,
        ]);

        foreach ([
            "/manage/api/users/{$target->public_id}",
            "/manage/api/service-managers/{$target->public_id}",
            '/manage/api/audit/login-failures',
            '/manage/api/audit/manage-actions',
        ] as $path) {
            $response = $this->actingAs($admin)
                ->withSession($this->manageSession($admin))
                ->getJson($path)
                ->assertOk();

            $this->assertNoSensitiveKeys($response->json());
        }
    }

    public function test_grant_revoke_no_op_and_cli_operations_are_all_audited(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession($this->manageSession($admin))
            ->postJson("/manage/api/service-managers/{$target->public_id}/grant")
            ->assertOk();
        $this->actingAs($admin)
            ->withSession($this->manageSession($admin))
            ->postJson("/manage/api/service-managers/{$target->public_id}/grant")
            ->assertOk();
        $this->actingAs($admin)
            ->withSession($this->manageSession($admin))
            ->postJson("/manage/api/service-managers/{$target->public_id}/revoke")
            ->assertOk();

        $cliTarget = User::factory()->create();
        $this->artisan('system-admin:add', ['user_public_id' => $cliTarget->public_id])->assertSuccessful();
        $this->artisan('system-admin:remove', ['user_public_id' => $cliTarget->public_id])->assertSuccessful();

        $this->assertSame(2, ManageActionLog::query()->where('action', 'service_manager.grant')->count());
        $this->assertTrue(
            ManageActionLog::query()
                ->where('action', 'service_manager.grant')
                ->latest('id')
                ->firstOrFail()
                ->metadata['no_op']
        );
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'service_manager.revoke']);
        $this->assertDatabaseHas('manage_action_logs', ['actor_type' => 'cli', 'action' => 'system_admin.grant']);
        $this->assertDatabaseHas('manage_action_logs', ['actor_type' => 'cli', 'action' => 'system_admin.revoke']);
    }

    private function userWithEmail(string $emailAddress): User
    {
        $user = User::factory()->create();
        $email = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => $emailAddress,
        ]);
        $user->forceFill(['primary_email_id' => $email->id])->save();

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function manageSession(User $user): array
    {
        return [
            'manage_authenticated_at' => now()->toISOString(),
            'manage_authenticated_user_id' => $user->id,
        ];
    }

    /**
     * @param  array<string|int, mixed>  $payload
     */
    private function assertNoSensitiveKeys(array $payload): void
    {
        $sensitiveKeys = [
            'password',
            'password_hash',
            'remember_token',
            'token',
            'access_token',
            'refresh_token',
        ];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $this->assertNotContains($key, $sensitiveKeys, "Sensitive key [{$key}] was exposed.");
            }

            if (is_array($value)) {
                $this->assertNoSensitiveKeys($value);
            }
        }
    }
}

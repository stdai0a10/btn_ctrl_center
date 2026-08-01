<?php

namespace Tests\Feature\Manage;

use App\Models\ManageActionLog;
use App\Models\ManageLoginLog;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
        $this->actor = User::factory()->create();
        $this->actor->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
    }

    public function test_login_failure_api_filters_records_without_exposing_credentials(): void
    {
        $target = User::factory()->create();
        ManageLoginLog::query()->create([
            'user_id' => $target->id,
            'email' => 'blocked@example.com',
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Test Browser',
            'success' => false,
            'failure_reason' => 'rate_limited',
            'locked_until' => now()->addMinutes(5),
        ]);
        ManageLoginLog::query()->create([
            'email' => 'other@example.com',
            'success' => false,
            'failure_reason' => 'invalid_credentials_or_permission',
        ]);

        $this->asManageUser()
            ->getJson("/manage/api/audit/login-failures?email=blocked&user_public_id={$target->public_id}&locked_only=1")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.email', 'blocked@example.com')
            ->assertJsonPath('data.items.0.user_agent', 'Test Browser')
            ->assertJsonMissingPath('data.items.0.password')
            ->assertJsonMissingPath('data.items.0.token');
    }

    public function test_manage_action_api_supports_actor_target_and_action_filters(): void
    {
        $target = User::factory()->create();
        ManageActionLog::query()->create([
            'actor_type' => 'manage_user',
            'actor_user_id' => $this->actor->id,
            'action' => 'service_manager.grant',
            'target_type' => 'user',
            'target_id' => $target->id,
            'target_public_id' => $target->public_id,
            'ip_address' => '198.51.100.20',
            'user_agent' => 'Audit Browser',
            'metadata' => ['no_op' => false],
        ]);

        $this->asManageUser()
            ->getJson("/manage/api/audit/manage-actions?actor={$this->actor->public_id}&action=service_manager&target_type=user&target_public_id={$target->public_id}&ip=198.51.100")
            ->assertOk()
            ->assertJsonFragment([
                'actor_public_id' => $this->actor->public_id,
                'target_public_id' => $target->public_id,
                'user_agent' => 'Audit Browser',
            ]);
    }

    public function test_audit_apis_require_access_and_specific_permissions(): void
    {
        $this->actor->removeRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $this->actor->givePermissionTo(['manage.access', 'audit.access']);

        $this->asManageUser()->getJson('/manage/api/audit/login-failures')->assertForbidden();
        $this->asManageUser()->getJson('/manage/api/audit/manage-actions')->assertForbidden();

        $this->actor->givePermissionTo('audit.login_failures.view');
        $this->asManageUser()->getJson('/manage/api/audit/login-failures')->assertOk();
        $this->asManageUser()->getJson('/manage/api/audit/manage-actions')->assertForbidden();
    }

    private function asManageUser(): static
    {
        return $this->actingAs($this->actor)->withSession([
            'manage_authenticated_at' => now()->toISOString(),
            'manage_authenticated_user_id' => $this->actor->id,
        ]);
    }
}

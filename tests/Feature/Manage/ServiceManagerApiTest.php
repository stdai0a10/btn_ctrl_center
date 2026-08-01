<?php

namespace Tests\Feature\Manage;

use App\Models\ManageActionLog;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagerApiTest extends TestCase
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

    public function test_list_defaults_to_service_managers_but_search_can_find_regular_users(): void
    {
        $manager = User::factory()->create(['name' => 'Existing Manager']);
        $manager->assignRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $regular = User::factory()->create(['name' => 'Searchable Regular']);

        $this->asManageUser()
            ->getJson('/manage/api/service-managers')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.public_id', $manager->public_id);

        $this->asManageUser()
            ->getJson('/manage/api/service-managers?search=Searchable')
            ->assertOk()
            ->assertJsonFragment(['public_id' => $regular->public_id]);
    }

    public function test_grant_and_revoke_are_idempotent_and_audited(): void
    {
        $target = User::factory()->create();

        $this->asManageUser()
            ->postJson("/manage/api/service-managers/{$target->public_id}/grant")
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->asManageUser()
            ->postJson("/manage/api/service-managers/{$target->public_id}/grant")
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertTrue($target->refresh()->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE));
        $this->assertDatabaseCount('manage_action_logs', 2);
        $this->assertTrue(
            ManageActionLog::query()->latest('id')->firstOrFail()->metadata['no_op']
        );

        $target->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $this->asManageUser()
            ->postJson("/manage/api/service-managers/{$target->public_id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $target->refresh();
        $this->assertFalse($target->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE));
        $this->assertTrue($target->hasRole(ManagementRbac::SYSTEM_ADMIN_ROLE));
    }

    public function test_batch_grant_and_revoke_require_corresponding_permissions(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $payload = ['user_public_ids' => [$first->public_id, $second->public_id]];

        $this->asManageUser()
            ->postJson('/manage/api/service-managers/grant-many', $payload)
            ->assertOk();
        $this->assertTrue($first->refresh()->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE));
        $this->assertTrue($second->refresh()->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE));

        $this->actor->removeRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        $this->actor->givePermissionTo(['manage.access', 'manage.service_managers.grant']);

        $this->asManageUser()
            ->postJson('/manage/api/service-managers/revoke-many', $payload)
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_be_granted_service_manager(): void
    {
        $target = User::factory()->create(['status' => 'disabled']);

        $this->asManageUser()
            ->postJson("/manage/api/service-managers/{$target->public_id}/grant")
            ->assertUnprocessable();

        $this->assertFalse($target->refresh()->hasRole(ManagementRbac::SERVICE_MANAGER_ROLE));
    }

    private function asManageUser(): static
    {
        return $this->actingAs($this->actor)->withSession([
            'manage_authenticated_at' => now()->toISOString(),
            'manage_authenticated_user_id' => $this->actor->id,
        ]);
    }
}

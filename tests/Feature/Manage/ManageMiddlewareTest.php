<?php

namespace Tests\Feature\Manage;

use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
    }

    public function test_guests_and_users_without_manage_verification_cannot_access_management_routes(): void
    {
        $this->get('/manage')->assertRedirect('/manage/login');
        $this->get('/manage/service-managers')->assertRedirect('/manage/login');
        $this->get('/manage/audit')->assertRedirect('/manage/login');
        $this->getJson('/manage/api/service-managers')->assertUnauthorized();

        $admin = User::factory()->create();
        $admin->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $this->actingAs($admin)->get('/manage/service-managers')->assertRedirect('/manage/login');
        $this->actingAs($admin)->getJson('/manage/api/service-managers')->assertUnauthorized();
    }

    public function test_manage_access_and_fresh_matching_session_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->get('/manage/service-managers')
            ->assertForbidden();

        $user->givePermissionTo('manage.access');

        $this->actingAs($user)
            ->withSession([
                'manage_authenticated_at' => now()->subMinutes(31)->toISOString(),
                'manage_authenticated_user_id' => $user->id,
            ])
            ->get('/manage/service-managers')
            ->assertRedirect('/manage/login');

        $this->actingAs($user)
            ->withSession([
                'manage_authenticated_at' => now()->toISOString(),
                'manage_authenticated_user_id' => $user->id + 1,
            ])
            ->getJson('/manage/api/service-managers')
            ->assertUnauthorized();
    }

    public function test_page_and_api_routes_require_their_specific_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['manage.access', 'manage.service_managers.view']);

        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->get('/manage/service-managers')
            ->assertOk();
        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->getJson('/manage/api/service-managers')
            ->assertOk();

        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->get("/manage/service-managers/{$user->public_id}")
            ->assertForbidden();
        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->postJson("/manage/api/service-managers/{$user->public_id}/grant")
            ->assertForbidden();
    }

    public function test_audit_routes_require_audit_access_and_specific_view_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['manage.access', 'audit.login_failures.view']);

        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->get('/manage/audit/login-failures')
            ->assertForbidden();

        $user->givePermissionTo('audit.access');

        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->get('/manage/audit/login-failures')
            ->assertOk();
        $this->actingAs($user)
            ->withSession($this->manageSession($user))
            ->get('/manage/audit/manage-actions')
            ->assertForbidden();
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
}

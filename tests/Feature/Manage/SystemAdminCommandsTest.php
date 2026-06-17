<?php

namespace Tests\Feature\Manage;

use App\Models\Auth\UserEmail;
use App\Models\ManageActionLog;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
    }

    public function test_cli_can_add_list_and_remove_the_only_system_admin(): void
    {
        $user = $this->userWithEmail('admin@example.com');

        $this->artisan('system-admin:add', ['user_public_id' => $user->public_id])
            ->expectsOutputToContain('Granted system administrator')
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->hasRole(ManagementRbac::SYSTEM_ADMIN_ROLE));
        $this->assertDatabaseHas('manage_action_logs', [
            'actor_type' => 'cli',
            'actor_user_id' => null,
            'action' => 'system_admin.grant',
            'target_public_id' => $user->public_id,
        ]);

        $this->artisan('system-admin:list')
            ->expectsTable([
                'Public ID',
                'Name',
                'Email',
                'Status',
                'System Admin Since',
                'Last Manage Login',
            ], [[
                $user->public_id,
                $user->displayName(),
                'admin@example.com',
                'active',
                (string) ManageActionLog::query()->where('action', 'system_admin.grant')->value('created_at'),
                '-',
            ]])
            ->assertSuccessful();

        $this->artisan('system-admin:remove', ['user_public_id' => $user->public_id])
            ->expectsOutputToContain('Removed system administrator')
            ->assertSuccessful();

        $this->assertFalse($user->refresh()->hasRole(ManagementRbac::SYSTEM_ADMIN_ROLE));
        $this->assertDatabaseHas('manage_action_logs', [
            'actor_type' => 'cli',
            'actor_user_id' => null,
            'action' => 'system_admin.revoke',
            'target_public_id' => $user->public_id,
        ]);
    }

    public function test_add_requires_an_active_existing_user_and_is_idempotent(): void
    {
        $inactive = User::factory()->create(['status' => 'disabled']);

        $this->artisan('system-admin:add', ['user_public_id' => 'MISSING'])
            ->assertFailed();
        $this->artisan('system-admin:add', ['user_public_id' => $inactive->public_id])
            ->assertFailed();

        $active = User::factory()->create();
        $this->artisan('system-admin:add', ['user_public_id' => $active->public_id])->assertSuccessful();
        $this->artisan('system-admin:add', ['user_public_id' => $active->public_id])->assertSuccessful();

        $this->assertSame(1, ManageActionLog::query()
            ->where('action', 'system_admin.grant')
            ->where('target_id', $active->id)
            ->count());
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
}

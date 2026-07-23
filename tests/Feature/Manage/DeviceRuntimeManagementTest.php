<?php

namespace Tests\Feature\Manage;

use App\Models\ButtonActionJob;
use App\Models\Device;
use App\Models\DeviceJwtToken;
use App\Models\Product;
use App\Models\ProductFunction;
use App\Models\Room;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRuntimeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ManagementRbacSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole(ManagementRbac::SERVICE_MANAGER_ROLE);
    }

    public function test_service_manager_can_view_device_runtime_and_button_jobs_without_sensitive_tokens(): void
    {
        [$device, $function] = $this->createRuntimeDevice();
        $user = User::factory()->create(['name' => 'Button User']);
        $job = ButtonActionJob::query()->create([
            'user_id' => $user->id,
            'request_id' => 'req-manage-view',
            'device_id' => $device->id,
            'product_function_id' => $function->id,
            'status' => ButtonActionJob::STATUS_QUEUED,
            'source' => ButtonActionJob::SOURCE_BUTTON,
            'payload' => ['secret' => 'must-not-leak', 'product_function_code' => $function->code],
            'front_end_timeout_at' => now()->addMinute(),
        ]);

        DeviceJwtToken::query()->create([
            'jti' => 'jwt-long-1',
            'device_id' => $device->id,
            'type' => DeviceJwtToken::TYPE_LONG,
            'token_version' => 1,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->asManageUser()
            ->getJson('/manage/api/device-runtime')
            ->assertOk()
            ->assertJsonPath('data.items.0.serial_number', $device->serial_number)
            ->assertJsonPath('data.items.0.tokens.active_token_count', 1)
            ->assertJsonMissingPath('data.items.0.secret_hash')
            ->assertJsonMissingPath('data.items.0.tokens.long_token_jti')
            ->assertJsonMissingPath('data.items.0.tokens.current_access_jti');

        $this->asManageUser()
            ->getJson('/manage/api/button-jobs')
            ->assertOk()
            ->assertJsonPath('data.items.0.public_id', $job->public_id)
            ->assertJsonPath('data.items.0.device.serial_number', $device->serial_number)
            ->assertJsonPath('data.items.0.function.code', $function->code)
            ->assertJsonMissingPath('data.items.0.payload')
            ->assertJsonMissingPath('data.items.0.result');

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'device_runtime.view',
        ]);
        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'button_jobs.view',
        ]);
    }

    public function test_runtime_management_requires_system_admin_and_logs_mutations(): void
    {
        [$device, $function] = $this->createRuntimeDevice([
            'serial_number' => 'DEVICE-RUNTIME-MANAGE',
            'long_token_jti' => 'long-jti',
            'current_access_jti' => 'access-jti',
            'current_access_expires_at' => now()->addMinutes(15),
            'token_version' => 4,
        ]);
        $user = User::factory()->create();
        $job = ButtonActionJob::query()->create([
            'user_id' => $user->id,
            'request_id' => 'req-cancel',
            'device_id' => $device->id,
            'product_function_id' => $function->id,
            'status' => ButtonActionJob::STATUS_RUNNING,
            'source' => ButtonActionJob::SOURCE_BUTTON,
            'locked_by_device_id' => $device->id,
            'started_at' => now(),
        ]);
        $device->forceFill([
            'runner_status' => 'running',
            'runner_current_job_id' => $job->id,
        ])->save();

        DeviceJwtToken::query()->create([
            'jti' => 'jwt-access-1',
            'device_id' => $device->id,
            'type' => DeviceJwtToken::TYPE_ACCESS,
            'token_version' => 4,
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->asManageUser()
            ->postJson('/manage/api/device-runtime/devices/DEVICE-RUNTIME-MANAGE/disable')
            ->assertForbidden();

        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $this->asManageUser()
            ->postJson('/manage/api/device-runtime/devices/DEVICE-RUNTIME-MANAGE/disable')
            ->assertOk()
            ->assertJsonPath('data.runtime.runner_status', 'disabled')
            ->assertJsonPath('data.tokens.current_access_expires_at', null);

        $device->refresh();
        $this->assertNotNull($device->runner_disabled_at);
        $this->assertNull($device->current_access_jti);
        $this->assertSame(5, $device->token_version);
        $this->assertSame(0, DeviceJwtToken::query()->whereNull('revoked_at')->count());

        $this->asManageUser()
            ->postJson('/manage/api/device-runtime/devices/DEVICE-RUNTIME-MANAGE/enable')
            ->assertOk()
            ->assertJsonPath('data.runtime.runner_disabled_at', null);

        $this->asManageUser()
            ->postJson("/manage/api/button-jobs/{$job->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', ButtonActionJob::STATUS_CANCELED);

        $this->assertDatabaseHas('button_action_jobs', [
            'public_id' => $job->public_id,
            'status' => ButtonActionJob::STATUS_CANCELED,
            'error_code' => 'MANAGE_CANCELED',
        ]);
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'device_runtime.disable']);
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'device_runtime.enable']);
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'button_jobs.cancel']);
    }

    public function test_system_admin_can_revoke_runtime_tokens_without_exposing_jti(): void
    {
        [$device] = $this->createRuntimeDevice([
            'serial_number' => 'DEVICE-REVOKE',
            'long_token_jti' => 'long-jti',
            'current_access_jti' => 'access-jti',
            'token_version' => 2,
        ]);
        DeviceJwtToken::query()->create([
            'jti' => 'jwt-access-revoke',
            'device_id' => $device->id,
            'type' => DeviceJwtToken::TYPE_ACCESS,
            'token_version' => 2,
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $this->asManageUser()
            ->postJson('/manage/api/device-runtime/devices/DEVICE-REVOKE/revoke-tokens')
            ->assertOk()
            ->assertJsonPath('data.tokens.token_version', 3)
            ->assertJsonMissingPath('data.tokens.long_token_jti')
            ->assertJsonMissingPath('data.tokens.current_access_jti');

        $this->assertSame(0, DeviceJwtToken::query()->whereNull('revoked_at')->count());
        $this->assertDatabaseHas('manage_action_logs', ['action' => 'device_runtime.revoke_tokens']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{Device, ProductFunction}
     */
    private function createRuntimeDevice(array $attributes = []): array
    {
        $owner = User::factory()->create();
        $room = Room::factory()->create(['created_by_user_id' => $owner->id]);
        $product = Product::factory()->create(['is_locked' => false]);
        $function = ProductFunction::factory()->create([
            'product_id' => $product->id,
            'is_enabled' => true,
        ]);
        $device = Device::factory()->create([
            'product_id' => $product->id,
            'current_room_id' => $room->id,
            'serial_number' => 'DEVICE-RUNTIME',
            'runner_status' => 'idle',
            'runner_last_seen_at' => now(),
            'runner_registered_at' => now(),
            ...$attributes,
        ]);

        return [$device, $function];
    }

    private function asManageUser(): static
    {
        return $this->actingAs($this->manager)->withSession($this->manageSession($this->manager));
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

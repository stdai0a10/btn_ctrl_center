<?php

namespace Tests\Feature\Manage;

use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\Room;
use App\Models\User;
use App\Support\ManagementRbac;
use Database\Seeders\ManagementRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceManagementApiTest extends TestCase
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

    public function test_service_manager_can_list_and_filter_assigned_and_unassigned_devices(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->create(['created_by_user_id' => $owner->id]);
        $assigned = Device::factory()->create([
            'serial_number' => 'DEVICE-ASSIGNED',
            'current_room_id' => $room->id,
            'name' => 'Living Room',
            'is_enabled' => false,
        ]);
        Device::factory()->create(['serial_number' => 'DEVICE-UNASSIGNED']);

        $this->asManageUser()
            ->getJson('/manage/api/devices?assignment_status=assigned&enabled=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.serial_number', $assigned->serial_number)
            ->assertJsonPath('data.items.0.room.public_id', $room->public_id)
            ->assertJsonPath('data.items.0.is_enabled', false)
            ->assertJsonMissingPath('data.items.0.secret_hash');

        $this->asManageUser()
            ->getJson('/manage/api/devices?assignment_status=unassigned')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.serial_number', 'DEVICE-UNASSIGNED')
            ->assertJsonPath('data.items.0.room', null);
    }

    public function test_service_manager_can_view_device_detail_with_paginated_transfer_logs(): void
    {
        $actor = User::factory()->create();
        $room = Room::factory()->create(['created_by_user_id' => $actor->id]);
        $device = Device::factory()->create([
            'serial_number' => 'DEVICE-DETAIL',
            'current_room_id' => $room->id,
        ]);

        DeviceTransferLog::query()->create([
            'device_id' => $device->id,
            'from_room_id' => null,
            'to_room_id' => $room->id,
            'transferred_by_user_id' => $actor->id,
            'to_room_public_id_snapshot' => $room->public_id,
            'transferred_by_user_public_id_snapshot' => $actor->public_id,
            'created_at' => now(),
        ]);

        $this->asManageUser()
            ->getJson('/manage/api/devices/device-detail')
            ->assertOk()
            ->assertJsonPath('data.serial_number', 'DEVICE-DETAIL')
            ->assertJsonPath('data.transfer_logs.pagination.per_page', 20)
            ->assertJsonPath('data.transfer_logs.items.0.to_room_public_id', $room->public_id)
            ->assertJsonMissingPath('data.secret_hash');

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'devices.detail.view',
            'target_type' => 'device',
            'target_public_id' => 'DEVICE-DETAIL',
        ]);
    }

    public function test_service_manager_can_view_paginated_devices_for_a_room(): void
    {
        $owner = User::factory()->create();
        $room = Room::factory()->create(['created_by_user_id' => $owner->id]);
        Device::factory()->count(2)->create(['current_room_id' => $room->id]);

        $this->asManageUser()
            ->getJson("/manage/api/rooms/{$room->public_id}/devices")
            ->assertOk()
            ->assertJsonPath('data.room.public_id', $room->public_id)
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'rooms.devices.view',
            'target_type' => 'room',
            'target_public_id' => $room->public_id,
        ]);
    }

    public function test_device_management_apis_require_manage_session_and_specific_permissions(): void
    {
        Device::factory()->create(['serial_number' => 'DEVICE-PROTECTED']);
        $regular = User::factory()->create();

        $this->actingAs($regular)
            ->withSession($this->manageSession($regular))
            ->getJson('/manage/api/devices')
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->getJson('/manage/api/devices')
            ->assertUnauthorized();
    }

    public function test_system_admin_can_create_normalized_unassigned_device_with_hashed_secret(): void
    {
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);

        $response = $this->asManageUser()
            ->withHeader('User-Agent', 'Device Admin Test')
            ->postJson('/manage/api/devices', [
                'serial_number' => '  device-new-001  ',
                'secret' => 'physical-secret',
                'secret_confirmation' => 'physical-secret',
            ])
            ->assertCreated()
            ->assertJsonPath('data.serial_number', 'DEVICE-NEW-001')
            ->assertJsonPath('data.room', null)
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonMissingPath('data.secret')
            ->assertJsonMissingPath('data.secret_hash');

        $device = Device::query()->where('serial_number', 'DEVICE-NEW-001')->firstOrFail();

        $this->assertNull($device->current_room_id);
        $this->assertNull($device->name);
        $this->assertTrue(Hash::check('physical-secret', $device->secret_hash));
        $this->assertNotSame('physical-secret', $device->secret_hash);

        $this->assertDatabaseHas('manage_action_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'devices.create',
            'target_type' => 'device',
            'target_id' => $device->id,
            'target_public_id' => 'DEVICE-NEW-001',
        ]);

        $encodedLog = json_encode(\App\Models\ManageActionLog::query()->latest('id')->firstOrFail()->metadata);
        $this->assertStringNotContainsString('physical-secret', $encodedLog);
        $this->assertStringNotContainsString('secret_hash', $encodedLog);
        $this->assertSame('DEVICE-NEW-001', $response->json('data.serial_number'));
    }

    public function test_service_manager_cannot_create_device(): void
    {
        $this->asManageUser()
            ->postJson('/manage/api/devices', [
                'serial_number' => 'DEVICE-FORBIDDEN',
                'secret' => 'physical-secret',
                'secret_confirmation' => 'physical-secret',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('devices', ['serial_number' => 'DEVICE-FORBIDDEN']);
    }

    public function test_normalized_duplicate_serial_is_rejected_without_logging_secret(): void
    {
        $this->manager->removeRole(ManagementRbac::SERVICE_MANAGER_ROLE);
        $this->manager->assignRole(ManagementRbac::SYSTEM_ADMIN_ROLE);
        Device::factory()->create(['serial_number' => 'DEVICE-DUPLICATE']);

        $this->asManageUser()
            ->postJson('/manage/api/devices', [
                'serial_number' => ' device-duplicate ',
                'secret' => 'must-not-leak',
                'secret_confirmation' => 'must-not-leak',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_SERIAL_ALREADY_EXISTS')
            ->assertJsonMissingPath('data.secret')
            ->assertJsonMissingPath('data.secret_hash');

        $this->assertSame(1, Device::query()->where('serial_number', 'DEVICE-DUPLICATE')->count());
        $this->assertDatabaseMissing('manage_action_logs', ['action' => 'devices.create']);
    }

    private function asManageUser(): static
    {
        return $this->actingAs($this->manager)->withSession($this->manageSession($this->manager));
    }

    private function manageSession(User $user): array
    {
        return [
            'manage_authenticated_at' => now()->toISOString(),
            'manage_authenticated_user_id' => $user->id,
        ];
    }
}

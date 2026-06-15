<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\House;
use App\Models\HouseInvitation;
use App\Models\HouseJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HouseDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_house_creation_makes_creator_owner_and_last_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)
            ->postJson('/api/houses', ['name' => 'Main Home'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Main Home')
            ->assertJsonPath('data.role', House::ROLE_OWNER);

        $house = House::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();

        $this->assertDatabaseHas('house_user', [
            'house_id' => $house->id,
            'user_id' => $owner->id,
            'role' => House::ROLE_OWNER,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/houses/{$house->public_id}/members/{$owner->public_id}/role", [
                'role' => House::ROLE_RESIDENT,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'HOUSE_LAST_OWNER_REQUIRED');
    }

    public function test_invitation_and_join_request_flows_add_residents(): void
    {
        [$owner, $invitee, $requester] = User::factory()->count(3)->create();
        $house = $this->createHouse($owner, 'Shared Home');

        $invitationId = $this->actingAs($owner)
            ->postJson("/api/houses/{$house->public_id}/invitations", [
                'invitee_public_id' => $invitee->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', HouseInvitation::STATUS_PENDING)
            ->json('data.id');

        $this->actingAs($invitee)
            ->postJson("/api/house-invitations/{$invitationId}/accept")
            ->assertOk();

        $this->assertDatabaseHas('house_user', [
            'house_id' => $house->id,
            'user_id' => $invitee->id,
            'role' => House::ROLE_RESIDENT,
        ]);

        $joinRequestId = $this->actingAs($requester)
            ->postJson("/api/houses/{$house->public_id}/join-requests")
            ->assertCreated()
            ->assertJsonPath('data.status', HouseJoinRequest::STATUS_PENDING)
            ->json('data.id');

        $this->actingAs($owner)
            ->postJson("/api/house-join-requests/{$joinRequestId}/accept")
            ->assertOk();

        $this->assertDatabaseHas('house_user', [
            'house_id' => $house->id,
            'user_id' => $requester->id,
            'role' => House::ROLE_RESIDENT,
        ]);
    }

    public function test_last_owner_leave_deletes_house_and_clears_locked_devices(): void
    {
        $owner = User::factory()->create();
        $house = $this->createHouse($owner, 'Solo Home');
        $device = Device::factory()->create([
            'current_house_id' => $house->id,
            'name' => 'Front Door',
            'is_locked' => true,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/houses/{$house->public_id}/leave")
            ->assertOk()
            ->assertJsonPath('data.house_deleted', true);

        $this->assertSoftDeleted('houses', ['id' => $house->id]);
        $this->assertNull($device->refresh()->current_house_id);
        $this->assertFalse($device->is_locked);
        $this->assertNull($device->name);
    }

    public function test_device_secret_lock_remove_and_transfer_rules(): void
    {
        [$firstOwner, $secondOwner] = User::factory()->count(2)->create();
        $firstHouse = $this->createHouse($firstOwner, 'First Home');
        $secondHouse = $this->createHouse($secondOwner, 'Second Home');
        $device = Device::factory()->create([
            'serial_number' => 'DEV-100',
            'secret_hash' => Hash::make('secret-100'),
        ]);

        $this->actingAs($firstOwner)
            ->postJson("/api/houses/{$firstHouse->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'wrong-secret',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_SECRET_INVALID');

        $this->actingAs($firstOwner)
            ->postJson("/api/houses/{$firstHouse->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'secret-100',
                'name' => 'Kitchen Button',
                'lock' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_locked', true);

        $this->actingAs($firstOwner)
            ->deleteJson("/api/houses/{$firstHouse->public_id}/devices/{$device->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_MUST_UNLOCK_BEFORE_REMOVE');

        $this->actingAs($secondOwner)
            ->postJson("/api/houses/{$secondHouse->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'secret-100',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_LOCKED');

        $this->actingAs($firstOwner)
            ->postJson("/api/houses/{$firstHouse->public_id}/devices/{$device->id}/unlock")
            ->assertOk();

        $this->actingAs($secondOwner)
            ->postJson("/api/houses/{$secondHouse->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'secret-100',
                'name' => 'Moved Button',
            ])
            ->assertCreated()
            ->assertJsonPath('data.current_house_id', $secondHouse->id);

        $this->assertSame($secondHouse->id, $device->refresh()->current_house_id);
        $this->assertSame('Moved Button', $device->name);
        $this->assertDatabaseHas('device_transfer_logs', [
            'device_id' => $device->id,
            'from_house_id' => $firstHouse->id,
            'to_house_id' => $secondHouse->id,
            'transferred_by_user_id' => $secondOwner->id,
        ]);
        $this->assertSame(2, DeviceTransferLog::query()->where('device_id', $device->id)->count());
    }

    private function createHouse(User $owner, string $name): House
    {
        $publicId = $this->actingAs($owner)
            ->postJson('/api/houses', ['name' => $name])
            ->assertCreated()
            ->json('data.public_id');

        return House::query()->where('public_id', $publicId)->firstOrFail();
    }
}

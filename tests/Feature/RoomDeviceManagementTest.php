<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\Room;
use App\Models\RoomInvitation;
use App\Models\RoomJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoomDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_creation_makes_creator_owner_and_last_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)
            ->postJson('/api/rooms', ['name' => 'Main Home'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Main Home')
            ->assertJsonPath('data.role', Room::ROLE_OWNER);

        $room = Room::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();

        $this->assertDatabaseHas('room_user', [
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'role' => Room::ROLE_OWNER,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/rooms/{$room->public_id}/members/{$owner->public_id}/role", [
                'role' => Room::ROLE_RESIDENT,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'ROOM_LAST_OWNER_REQUIRED');
    }

    public function test_invitation_and_join_request_flows_add_residents(): void
    {
        [$owner, $invitee, $requester] = User::factory()->count(3)->create();
        $room = $this->createRoom($owner, 'Shared Home');

        $invitationId = $this->actingAs($owner)
            ->postJson("/api/rooms/{$room->public_id}/invitations", [
                'invitee_public_id' => $invitee->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', RoomInvitation::STATUS_PENDING)
            ->json('data.id');

        $this->actingAs($invitee)
            ->postJson("/api/room-invitations/{$invitationId}/accept")
            ->assertOk();

        $this->assertDatabaseHas('room_user', [
            'room_id' => $room->id,
            'user_id' => $invitee->id,
            'role' => Room::ROLE_RESIDENT,
        ]);

        $joinRequestId = $this->actingAs($requester)
            ->postJson("/api/rooms/{$room->public_id}/join-requests")
            ->assertCreated()
            ->assertJsonPath('data.status', RoomJoinRequest::STATUS_PENDING)
            ->json('data.id');

        $this->actingAs($owner)
            ->postJson("/api/room-join-requests/{$joinRequestId}/accept")
            ->assertOk();

        $this->assertDatabaseHas('room_user', [
            'room_id' => $room->id,
            'user_id' => $requester->id,
            'role' => Room::ROLE_RESIDENT,
        ]);
    }

    public function test_last_owner_leave_deletes_room_and_clears_locked_devices(): void
    {
        $owner = User::factory()->create();
        $room = $this->createRoom($owner, 'Solo Home');
        $device = Device::factory()->create([
            'current_room_id' => $room->id,
            'name' => 'Front Door',
            'is_locked' => true,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/rooms/{$room->public_id}/leave")
            ->assertOk()
            ->assertJsonPath('data.room_deleted', true);

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
        $this->assertNull($device->refresh()->current_room_id);
        $this->assertFalse($device->is_locked);
        $this->assertNull($device->name);
    }

    public function test_device_secret_lock_remove_and_transfer_rules(): void
    {
        [$firstOwner, $secondOwner] = User::factory()->count(2)->create();
        $firstRoom = $this->createRoom($firstOwner, 'First Home');
        $secondRoom = $this->createRoom($secondOwner, 'Second Home');
        $device = Device::factory()->create([
            'serial_number' => 'DEV-100',
            'secret_hash' => Hash::make('secret-100'),
        ]);

        $this->actingAs($firstOwner)
            ->postJson("/api/rooms/{$firstRoom->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'wrong-secret',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_SECRET_INVALID');

        $this->actingAs($firstOwner)
            ->postJson("/api/rooms/{$firstRoom->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'secret-100',
                'name' => 'Kitchen Button',
                'lock' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_locked', true);

        $this->actingAs($firstOwner)
            ->deleteJson("/api/rooms/{$firstRoom->public_id}/devices/{$device->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_MUST_UNLOCK_BEFORE_REMOVE');

        $this->actingAs($secondOwner)
            ->postJson("/api/rooms/{$secondRoom->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'secret-100',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEVICE_LOCKED');

        $this->actingAs($firstOwner)
            ->postJson("/api/rooms/{$firstRoom->public_id}/devices/{$device->id}/unlock")
            ->assertOk();

        $this->actingAs($secondOwner)
            ->postJson("/api/rooms/{$secondRoom->public_id}/devices", [
                'serial_number' => 'DEV-100',
                'secret' => 'secret-100',
                'name' => 'Moved Button',
            ])
            ->assertCreated()
            ->assertJsonPath('data.current_room_id', $secondRoom->id);

        $this->assertSame($secondRoom->id, $device->refresh()->current_room_id);
        $this->assertSame('Moved Button', $device->name);
        $this->assertDatabaseHas('device_transfer_logs', [
            'device_id' => $device->id,
            'from_room_id' => $firstRoom->id,
            'to_room_id' => $secondRoom->id,
            'transferred_by_user_id' => $secondOwner->id,
        ]);
        $this->assertSame(2, DeviceTransferLog::query()->where('device_id', $device->id)->count());
    }

    private function createRoom(User $owner, string $name): Room
    {
        $publicId = $this->actingAs($owner)
            ->postJson('/api/rooms', ['name' => $name])
            ->assertCreated()
            ->json('data.public_id');

        return Room::query()->where('public_id', $publicId)->firstOrFail();
    }
}

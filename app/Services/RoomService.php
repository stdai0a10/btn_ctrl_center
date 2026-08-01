<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Room;
use App\Models\RoomInvitation;
use App\Models\RoomJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function create(User $creator, string $name): Room
    {
        return DB::transaction(function () use ($creator, $name): Room {
            $room = Room::query()->create([
                'name' => $name,
                'created_by_user_id' => $creator->id,
            ]);

            $room->members()->attach($creator->id, [
                'role' => Room::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            return $room->load('members');
        });
    }

    public function update(Room $room, string $name): Room
    {
        $room->forceFill(['name' => $name])->save();

        return $room->refresh();
    }

    public function delete(Room $room): void
    {
        DB::transaction(function () use ($room): void {
            Device::query()
                ->where('current_room_id', $room->id)
                ->update([
                    'current_room_id' => null,
                    'name' => null,
                    'is_locked' => false,
                    'is_enabled' => true,
                ]);

            RoomInvitation::query()
                ->where('room_id', $room->id)
                ->where('status', RoomInvitation::STATUS_PENDING)
                ->update([
                    'status' => RoomInvitation::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            RoomJoinRequest::query()
                ->where('room_id', $room->id)
                ->where('status', RoomJoinRequest::STATUS_PENDING)
                ->update([
                    'status' => RoomJoinRequest::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            $room->members()->detach();
            $room->delete();
        });
    }
}

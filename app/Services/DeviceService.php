<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\Room;
use App\Models\User;
use App\Support\DeviceSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeviceService
{
    public function attachToRoom(Room $room, User $actor, string $serialNumber, string $secret, ?string $name, bool $lock): Device
    {
        $serialNumber = DeviceSerial::normalize($serialNumber);

        return DB::transaction(function () use ($room, $actor, $serialNumber, $secret, $name, $lock): Device {
            $device = Device::query()
                ->where('serial_number', $serialNumber)
                ->lockForUpdate()
                ->first();

            if ($device === null) {
                throw new ApiException('Device not found.', 'DEVICE_NOT_FOUND', 404);
            }

            if (! Hash::check($secret, $device->secret_hash)) {
                throw new ApiException('Device secret is invalid.', 'DEVICE_SECRET_INVALID');
            }

            if ((int) $device->current_room_id === (int) $room->id) {
                throw new ApiException('Device already belongs to this room.', 'DEVICE_ALREADY_IN_THIS_ROOM');
            }

            if ($device->current_room_id !== null && $device->is_locked) {
                throw new ApiException('Device is locked.', 'DEVICE_LOCKED');
            }

            $fromRoomId = $device->current_room_id;
            $fromRoomPublicId = $fromRoomId === null
                ? null
                : Room::query()->withTrashed()->whereKey($fromRoomId)->value('public_id');

            $device->forceFill([
                'current_room_id' => $room->id,
                'name' => $name,
                'is_locked' => $lock,
                'is_enabled' => true,
            ])->save();

            DeviceTransferLog::query()->create([
                'device_id' => $device->id,
                'from_room_id' => $fromRoomId,
                'to_room_id' => $room->id,
                'transferred_by_user_id' => $actor->id,
                'from_room_public_id_snapshot' => $fromRoomPublicId,
                'to_room_public_id_snapshot' => $room->public_id,
                'transferred_by_user_public_id_snapshot' => $actor->public_id,
                'created_at' => now(),
            ]);

            return $device->refresh();
        });
    }

    public function rename(Room $room, Device $device, ?string $name): Device
    {
        $this->ensureDeviceInRoom($room, $device);

        $device->forceFill(['name' => $name])->save();

        return $device->refresh();
    }

    public function lock(Room $room, Device $device): Device
    {
        $this->ensureDeviceInRoom($room, $device);

        $device->forceFill(['is_locked' => true])->save();

        return $device->refresh();
    }

    public function unlock(Room $room, Device $device): Device
    {
        $this->ensureDeviceInRoom($room, $device);

        $device->forceFill(['is_locked' => false])->save();

        return $device->refresh();
    }

    public function enable(Room $room, Device $device): Device
    {
        $this->ensureDeviceInRoom($room, $device);

        $device->forceFill(['is_enabled' => true])->save();

        return $device->refresh();
    }

    public function disable(Room $room, Device $device): Device
    {
        $this->ensureDeviceInRoom($room, $device);

        $device->forceFill(['is_enabled' => false])->save();

        return $device->refresh();
    }

    public function removeFromRoom(Room $room, Device $device): void
    {
        $this->ensureDeviceInRoom($room, $device);

        if ($device->is_locked) {
            throw new ApiException('Device must be unlocked before removal.', 'DEVICE_MUST_UNLOCK_BEFORE_REMOVE');
        }

        $device->forceFill([
            'current_room_id' => null,
            'name' => null,
            'is_locked' => false,
            'is_enabled' => true,
        ])->save();
    }

    private function ensureDeviceInRoom(Room $room, Device $device): void
    {
        if ((int) $device->current_room_id !== (int) $room->id) {
            throw new ApiException('Device is not in this room.', 'DEVICE_NOT_IN_ROOM', 404);
        }
    }
}

<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\House;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeviceService
{
    public function attachToHouse(House $house, User $actor, string $serialNumber, string $secret, ?string $name, bool $lock): Device
    {
        return DB::transaction(function () use ($house, $actor, $serialNumber, $secret, $name, $lock): Device {
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

            if ((int) $device->current_house_id === (int) $house->id) {
                throw new ApiException('Device already belongs to this house.', 'DEVICE_ALREADY_IN_THIS_HOUSE');
            }

            if ($device->current_house_id !== null && $device->is_locked) {
                throw new ApiException('Device is locked.', 'DEVICE_LOCKED');
            }

            $fromHouseId = $device->current_house_id;

            $device->forceFill([
                'current_house_id' => $house->id,
                'name' => $name,
                'is_locked' => $lock,
            ])->save();

            DeviceTransferLog::query()->create([
                'device_id' => $device->id,
                'from_house_id' => $fromHouseId,
                'to_house_id' => $house->id,
                'transferred_by_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            return $device->refresh();
        });
    }

    public function rename(House $house, Device $device, ?string $name): Device
    {
        $this->ensureDeviceInHouse($house, $device);

        $device->forceFill(['name' => $name])->save();

        return $device->refresh();
    }

    public function lock(House $house, Device $device): Device
    {
        $this->ensureDeviceInHouse($house, $device);

        $device->forceFill(['is_locked' => true])->save();

        return $device->refresh();
    }

    public function unlock(House $house, Device $device): Device
    {
        $this->ensureDeviceInHouse($house, $device);

        $device->forceFill(['is_locked' => false])->save();

        return $device->refresh();
    }

    public function removeFromHouse(House $house, Device $device): void
    {
        $this->ensureDeviceInHouse($house, $device);

        if ($device->is_locked) {
            throw new ApiException('Device must be unlocked before removal.', 'DEVICE_MUST_UNLOCK_BEFORE_REMOVE');
        }

        $device->forceFill([
            'current_house_id' => null,
            'name' => null,
            'is_locked' => false,
        ])->save();
    }

    private function ensureDeviceInHouse(House $house, Device $device): void
    {
        if ((int) $device->current_house_id !== (int) $house->id) {
            throw new ApiException('Device is not in this house.', 'DEVICE_NOT_IN_HOUSE', 404);
        }
    }
}

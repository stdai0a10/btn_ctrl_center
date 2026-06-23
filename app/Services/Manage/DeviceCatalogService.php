<?php

namespace App\Services\Manage;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Support\DeviceSerial;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeviceCatalogService
{
    public function __construct(private readonly ManageActionLogger $logger) {}

    public function create(Request $request, string $serialNumber, string $secret): Device
    {
        $serialNumber = DeviceSerial::normalize($serialNumber);

        if (Device::query()->where('serial_number', $serialNumber)->exists()) {
            throw $this->duplicateSerial();
        }

        try {
            return DB::transaction(function () use ($request, $serialNumber, $secret): Device {
                $device = Device::query()->create([
                    'serial_number' => $serialNumber,
                    'secret_hash' => Hash::make($secret),
                    'current_room_id' => null,
                    'name' => null,
                    'is_locked' => false,
                    'is_enabled' => true,
                ]);

                $this->logger->forManageUser(
                    request: $request,
                    action: 'devices.create',
                    targetType: 'device',
                    targetId: $device->id,
                    targetPublicId: $device->serial_number,
                    metadata: [
                        'before' => null,
                        'after' => [
                            'serial_number' => $device->serial_number,
                            'current_room_public_id' => null,
                            'is_locked' => false,
                            'is_enabled' => true,
                        ],
                    ],
                );

                return $device;
            });
        } catch (QueryException $exception) {
            if (Device::query()->where('serial_number', $serialNumber)->exists()) {
                throw $this->duplicateSerial();
            }

            throw $exception;
        }
    }

    private function duplicateSerial(): ApiException
    {
        return new ApiException(
            'Device serial number already exists.',
            'DEVICE_SERIAL_ALREADY_EXISTS',
        );
    }
}

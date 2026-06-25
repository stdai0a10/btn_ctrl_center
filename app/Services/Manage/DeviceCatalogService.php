<?php

namespace App\Services\Manage;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Product;
use App\Support\DeviceSerial;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeviceCatalogService
{
    public function __construct(private readonly ManageActionLogger $logger) {}

    public function create(Request $request, string $productPublicId, string $serialNumber, string $secret): Device
    {
        $serialNumber = DeviceSerial::normalize($serialNumber);
        $product = Product::query()->where('public_id', $productPublicId)->first();

        if ($product === null) {
            throw new ApiException('Product not found.', 'PRODUCT_NOT_FOUND', 404);
        }

        if (Device::query()->where('serial_number', $serialNumber)->exists()) {
            throw $this->duplicateSerial();
        }

        try {
            return DB::transaction(function () use ($request, $product, $serialNumber, $secret): Device {
                $device = Device::query()->create([
                    'product_id' => $product->id,
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
                            'product_public_id' => $product->public_id,
                            'product_model_number' => $product->model_number,
                            'current_room_public_id' => null,
                            'is_locked' => false,
                            'is_enabled' => true,
                        ],
                    ],
                );

                return $device->load('product');
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

<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => null,
            'serial_number' => 'DEV-'.Str::upper(Str::random(10)),
            'secret_hash' => Hash::make('device-secret'),
            'current_room_id' => null,
            'name' => null,
            'is_locked' => false,
            'is_enabled' => true,
        ];
    }
}

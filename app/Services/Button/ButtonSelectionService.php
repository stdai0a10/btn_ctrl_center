<?php

namespace App\Services\Button;

use App\Models\Device;
use App\Models\User;

class ButtonSelectionService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function selectableTargets(User $user): array
    {
        return Device::query()
            ->with(['currentRoom', 'product.functions'])
            ->whereNotNull('current_room_id')
            ->whereHas('currentRoom.members', fn ($query) => $query->whereKey($user->id))
            ->where('is_system_disabled', false)
            ->where('is_enabled', true)
            ->whereNotNull('product_id')
            ->orderBy('serial_number')
            ->get()
            ->map(function (Device $device): array {
                $functions = $device->product?->functions
                    ->where('is_enabled', true)
                    ->sortBy('description')
                    ->values()
                    ->map(fn ($function): array => [
                        'code' => $function->code,
                        'description' => $function->description,
                    ])
                    ->all() ?? [];

                return [
                    'device' => [
                        'serial_number' => $device->serial_number,
                        'name' => $device->name,
                        'room' => $device->currentRoom === null ? null : [
                            'public_id' => $device->currentRoom->public_id,
                            'name' => $device->currentRoom->name,
                        ],
                        'product' => $device->product === null ? null : [
                            'model_number' => $device->product->model_number,
                            'name' => $device->product->name,
                        ],
                    ],
                    'functions' => $functions,
                ];
            })
            ->filter(fn (array $item): bool => count($item['functions']) > 0)
            ->values()
            ->all();
    }
}

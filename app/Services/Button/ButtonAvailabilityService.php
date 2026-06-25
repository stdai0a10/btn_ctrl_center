<?php

namespace App\Services\Button;

use App\Models\Device;
use App\Models\ProductFunction;
use App\Models\User;

class ButtonAvailabilityService
{
    /**
     * @return array{available: bool, reason: ?string, message: ?string}
     */
    public function for(User $user, Device $device, ProductFunction $function): array
    {
        $device->loadMissing(['currentRoom.members', 'product']);
        $function->loadMissing('product');

        if ($device->currentRoom === null || ! $device->currentRoom->isMember($user)) {
            return $this->unavailable('device_unavailable', '設備目前不可用');
        }

        if ($device->is_system_disabled || $device->runner_disabled_at !== null) {
            return $this->unavailable('device_system_disabled', '設備已被系統鎖定');
        }

        if (! $device->is_enabled) {
            return $this->unavailable('device_disabled_by_owner', '設備已被房主停用');
        }

        if (! $this->isOnline($device)) {
            return $this->unavailable('device_offline', '設備已離線');
        }

        if (! $this->functionIsUsable($device, $function)) {
            return $this->unavailable('function_unavailable', '功能無法使用');
        }

        return [
            'available' => true,
            'reason' => null,
            'message' => null,
        ];
    }

    private function isOnline(Device $device): bool
    {
        if (! in_array($device->runner_status, ['idle', 'running'], true)) {
            return false;
        }

        return $device->runner_last_seen_at !== null
            && $device->runner_last_seen_at->greaterThan(now()->subMinutes(5));
    }

    private function functionIsUsable(Device $device, ProductFunction $function): bool
    {
        if ($device->product_id === null || (int) $function->product_id !== (int) $device->product_id) {
            return false;
        }

        if (! $function->is_enabled) {
            return false;
        }

        $device->loadMissing('product');

        return ! (bool) $device->product?->is_locked;
    }

    /**
     * @return array{available: false, reason: string, message: string}
     */
    private function unavailable(string $reason, string $message): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'message' => $message,
        ];
    }
}

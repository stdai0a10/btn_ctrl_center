<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\Room;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user, Room $room): bool
    {
        return $room->isMember($user);
    }

    public function manage(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }

    public function view(User $user, Device $device): bool
    {
        return $device->currentRoom?->isMember($user) ?? false;
    }
}

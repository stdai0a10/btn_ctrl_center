<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\House;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user, House $house): bool
    {
        return $house->isMember($user);
    }

    public function manage(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }

    public function view(User $user, Device $device): bool
    {
        return $device->currentHouse?->isMember($user) ?? false;
    }
}

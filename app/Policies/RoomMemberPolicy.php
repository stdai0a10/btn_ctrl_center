<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomMemberPolicy
{
    public function remove(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }

    public function updateRole(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }
}

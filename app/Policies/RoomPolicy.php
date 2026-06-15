<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Room $room): bool
    {
        return $room->isMember($user);
    }

    public function update(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }

    public function delete(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }

    public function manageMembers(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }

    public function invite(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }

    public function manageDevices(User $user, Room $room): bool
    {
        return $room->isOwner($user);
    }
}

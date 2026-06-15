<?php

namespace App\Policies;

use App\Models\House;
use App\Models\User;

class HousePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, House $house): bool
    {
        return $house->isMember($user);
    }

    public function update(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }

    public function delete(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }

    public function manageMembers(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }

    public function invite(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }

    public function manageDevices(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }
}

<?php

namespace App\Policies;

use App\Models\House;
use App\Models\User;

class HouseMemberPolicy
{
    public function remove(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }

    public function updateRole(User $user, House $house): bool
    {
        return $house->isOwner($user);
    }
}

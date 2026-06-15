<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\House;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HouseMemberService
{
    public function removeMember(House $house, User $actor, User $member): void
    {
        if ($actor->is($member)) {
            throw new ApiException('Owner cannot remove self.', 'HOUSE_OWNER_CANNOT_REMOVE_SELF');
        }

        DB::transaction(function () use ($house, $member): void {
            $membership = $this->lockedMembership($house, $member);

            if ($membership === null) {
                throw new ApiException('User is not a house member.', 'HOUSE_MEMBER_NOT_FOUND', 404);
            }

            if ($membership->pivot->role === House::ROLE_OWNER && $this->ownerCount($house) <= 1) {
                throw new ApiException('Cannot remove the last owner.', 'HOUSE_LAST_OWNER_REQUIRED');
            }

            $house->members()->detach($member->id);
        });
    }

    public function changeRole(House $house, User $actor, User $member, string $role): void
    {
        DB::transaction(function () use ($house, $actor, $member, $role): void {
            $membership = $this->lockedMembership($house, $member);

            if ($membership === null) {
                throw new ApiException('User is not a house member.', 'HOUSE_MEMBER_NOT_FOUND', 404);
            }

            if (
                $actor->is($member)
                && $membership->pivot->role === House::ROLE_OWNER
                && $role === House::ROLE_RESIDENT
                && $this->ownerCount($house) <= 1
            ) {
                throw new ApiException('Cannot demote the last owner.', 'HOUSE_LAST_OWNER_REQUIRED');
            }

            if (
                $membership->pivot->role === House::ROLE_OWNER
                && $role === House::ROLE_RESIDENT
                && $this->ownerCount($house) <= 1
            ) {
                throw new ApiException('Cannot demote the last owner.', 'HOUSE_LAST_OWNER_REQUIRED');
            }

            $house->members()->updateExistingPivot($member->id, [
                'role' => $role,
            ]);
        });
    }

    public function leave(House $house, User $member): bool
    {
        return DB::transaction(function () use ($house, $member): bool {
            $membership = $this->lockedMembership($house, $member);

            if ($membership === null) {
                throw new ApiException('User is not a house member.', 'HOUSE_MEMBER_NOT_FOUND', 404);
            }

            $ownerCount = $this->ownerCount($house);
            $memberCount = DB::table('house_user')
                ->where('house_id', $house->id)
                ->lockForUpdate()
                ->count();

            if ($membership->pivot->role === House::ROLE_OWNER && $ownerCount <= 1 && $memberCount > 1) {
                throw new ApiException('Cannot leave the house without an owner.', 'HOUSE_LAST_OWNER_REQUIRED');
            }

            $house->members()->detach($member->id);

            if ($membership->pivot->role === House::ROLE_OWNER && $ownerCount <= 1 && $memberCount === 1) {
                $house->delete();

                return true;
            }

            return false;
        });
    }

    private function lockedMembership(House $house, User $member): ?User
    {
        return $house->members()
            ->whereKey($member->id)
            ->lockForUpdate()
            ->first();
    }

    private function ownerCount(House $house): int
    {
        return DB::table('house_user')
            ->where('house_id', $house->id)
            ->where('role', House::ROLE_OWNER)
            ->lockForUpdate()
            ->count();
    }
}

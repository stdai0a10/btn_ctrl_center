<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Room;
use App\Models\RoomInvitation;
use App\Models\RoomJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoomMemberService
{
    public function removeMember(Room $room, User $actor, User $member): void
    {
        if ($actor->is($member)) {
            throw new ApiException('Owner cannot remove self.', 'ROOM_OWNER_CANNOT_REMOVE_SELF');
        }

        DB::transaction(function () use ($room, $member): void {
            $membership = $this->lockedMembership($room, $member);

            if ($membership === null) {
                throw new ApiException('User is not a room member.', 'ROOM_MEMBER_NOT_FOUND', 404);
            }

            if ($membership->pivot->role === Room::ROLE_OWNER && $this->ownerCount($room) <= 1) {
                throw new ApiException('Cannot remove the last owner.', 'ROOM_LAST_OWNER_REQUIRED');
            }

            $room->members()->detach($member->id);
        });
    }

    public function changeRole(Room $room, User $actor, User $member, string $role): void
    {
        DB::transaction(function () use ($room, $actor, $member, $role): void {
            $membership = $this->lockedMembership($room, $member);

            if ($membership === null) {
                throw new ApiException('User is not a room member.', 'ROOM_MEMBER_NOT_FOUND', 404);
            }

            if (
                $actor->is($member)
                && $membership->pivot->role === Room::ROLE_OWNER
                && $role === Room::ROLE_RESIDENT
                && $this->ownerCount($room) <= 1
            ) {
                throw new ApiException('Cannot demote the last owner.', 'ROOM_LAST_OWNER_REQUIRED');
            }

            if (
                $membership->pivot->role === Room::ROLE_OWNER
                && $role === Room::ROLE_RESIDENT
                && $this->ownerCount($room) <= 1
            ) {
                throw new ApiException('Cannot demote the last owner.', 'ROOM_LAST_OWNER_REQUIRED');
            }

            $room->members()->updateExistingPivot($member->id, [
                'role' => $role,
            ]);
        });
    }

    public function leave(Room $room, User $member): bool
    {
        return DB::transaction(function () use ($room, $member): bool {
            $membership = $this->lockedMembership($room, $member);

            if ($membership === null) {
                throw new ApiException('User is not a room member.', 'ROOM_MEMBER_NOT_FOUND', 404);
            }

            $ownerCount = $this->ownerCount($room);
            $memberCount = DB::table('room_user')
                ->where('room_id', $room->id)
                ->lockForUpdate()
                ->get()
                ->count();

            if ($membership->pivot->role === Room::ROLE_OWNER && $ownerCount <= 1 && $memberCount > 1) {
                throw new ApiException('Cannot leave the room without an owner.', 'ROOM_LAST_OWNER_REQUIRED');
            }

            $room->members()->detach($member->id);

            if ($membership->pivot->role === Room::ROLE_OWNER && $ownerCount <= 1 && $memberCount === 1) {
                Device::query()
                    ->where('current_room_id', $room->id)
                    ->update([
                        'current_room_id' => null,
                        'name' => null,
                        'is_locked' => false,
                    ]);

                RoomInvitation::query()
                    ->where('room_id', $room->id)
                    ->where('status', RoomInvitation::STATUS_PENDING)
                    ->update([
                        'status' => RoomInvitation::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                    ]);

                RoomJoinRequest::query()
                    ->where('room_id', $room->id)
                    ->where('status', RoomJoinRequest::STATUS_PENDING)
                    ->update([
                        'status' => RoomJoinRequest::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                    ]);

                $room->delete();

                return true;
            }

            return false;
        });
    }

    private function lockedMembership(Room $room, User $member): ?User
    {
        return $room->members()
            ->whereKey($member->id)
            ->lockForUpdate()
            ->first();
    }

    private function ownerCount(Room $room): int
    {
        return DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('role', Room::ROLE_OWNER)
            ->lockForUpdate()
            ->get()
            ->count();
    }
}

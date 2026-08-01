<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Room;
use App\Models\RoomInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoomInvitationService
{
    public function invite(Room $room, User $inviter, User $invitee): RoomInvitation
    {
        return DB::transaction(function () use ($room, $inviter, $invitee): RoomInvitation {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->isMember($invitee)) {
                throw new ApiException('User is already a room member.', 'ROOM_MEMBER_ALREADY_EXISTS');
            }

            if ($this->pendingInvitation($room, $invitee)->lockForUpdate()->exists()) {
                throw new ApiException('A pending invitation already exists.', 'ROOM_INVITATION_ALREADY_PENDING');
            }

            return RoomInvitation::query()->create([
                'room_id' => $room->id,
                'inviter_user_id' => $inviter->id,
                'invitee_user_id' => $invitee->id,
                'status' => RoomInvitation::STATUS_PENDING,
            ])->load(['room', 'inviter', 'invitee']);
        });
    }

    public function cancel(RoomInvitation $invitation): void
    {
        $this->transition($invitation, RoomInvitation::STATUS_CANCELLED, 'cancelled_at');
    }

    public function accept(RoomInvitation $invitation, User $invitee): void
    {
        if ($invitation->invitee_user_id !== $invitee->id) {
            throw new ApiException('This invitation belongs to another user.', 'ROOM_INVITATION_FORBIDDEN', 403);
        }

        DB::transaction(function () use ($invitation, $invitee): void {
            $locked = RoomInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            $this->ensurePending($locked);

            $room = Room::query()->lockForUpdate()->findOrFail($locked->room_id);

            if ($room->isMember($invitee)) {
                throw new ApiException('User is already a room member.', 'ROOM_MEMBER_ALREADY_EXISTS');
            }

            $room->members()->attach($invitee->id, [
                'role' => Room::ROLE_RESIDENT,
                'joined_at' => now(),
            ]);

            $locked->forceFill([
                'status' => RoomInvitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ])->save();
        });
    }

    public function ignore(RoomInvitation $invitation, User $invitee): void
    {
        if ($invitation->invitee_user_id !== $invitee->id) {
            throw new ApiException('This invitation belongs to another user.', 'ROOM_INVITATION_FORBIDDEN', 403);
        }

        $this->transition($invitation, RoomInvitation::STATUS_IGNORED, 'ignored_at');
    }

    private function transition(RoomInvitation $invitation, string $status, string $timestamp): void
    {
        DB::transaction(function () use ($invitation, $status, $timestamp): void {
            $locked = RoomInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            $this->ensurePending($locked);

            $locked->forceFill([
                'status' => $status,
                $timestamp => now(),
            ])->save();
        });
    }

    private function ensurePending(RoomInvitation $invitation): void
    {
        if ($invitation->status !== RoomInvitation::STATUS_PENDING) {
            throw new ApiException('Invitation is not pending.', 'ROOM_INVITATION_NOT_PENDING');
        }
    }

    private function pendingInvitation(Room $room, User $invitee)
    {
        return RoomInvitation::query()
            ->where('room_id', $room->id)
            ->where('invitee_user_id', $invitee->id)
            ->where('status', RoomInvitation::STATUS_PENDING);
    }
}

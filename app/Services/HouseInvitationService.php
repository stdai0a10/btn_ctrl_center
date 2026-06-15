<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\House;
use App\Models\HouseInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HouseInvitationService
{
    public function invite(House $house, User $inviter, User $invitee): HouseInvitation
    {
        if ($house->isMember($invitee)) {
            throw new ApiException('User is already a house member.', 'HOUSE_MEMBER_ALREADY_EXISTS');
        }

        if ($this->pendingInvitation($house, $invitee)->exists()) {
            throw new ApiException('A pending invitation already exists.', 'HOUSE_INVITATION_ALREADY_PENDING');
        }

        return HouseInvitation::query()->create([
            'house_id' => $house->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'status' => HouseInvitation::STATUS_PENDING,
        ])->load(['house', 'inviter', 'invitee']);
    }

    public function cancel(HouseInvitation $invitation): void
    {
        $this->transition($invitation, HouseInvitation::STATUS_CANCELLED, 'cancelled_at');
    }

    public function accept(HouseInvitation $invitation, User $invitee): void
    {
        if ($invitation->invitee_user_id !== $invitee->id) {
            throw new ApiException('This invitation belongs to another user.', 'HOUSE_INVITATION_FORBIDDEN', 403);
        }

        DB::transaction(function () use ($invitation, $invitee): void {
            $locked = HouseInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            $this->ensurePending($locked);

            $house = House::query()->lockForUpdate()->findOrFail($locked->house_id);

            if ($house->isMember($invitee)) {
                throw new ApiException('User is already a house member.', 'HOUSE_MEMBER_ALREADY_EXISTS');
            }

            $house->members()->attach($invitee->id, [
                'role' => House::ROLE_RESIDENT,
                'joined_at' => now(),
            ]);

            $locked->forceFill([
                'status' => HouseInvitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ])->save();
        });
    }

    public function ignore(HouseInvitation $invitation, User $invitee): void
    {
        if ($invitation->invitee_user_id !== $invitee->id) {
            throw new ApiException('This invitation belongs to another user.', 'HOUSE_INVITATION_FORBIDDEN', 403);
        }

        $this->transition($invitation, HouseInvitation::STATUS_IGNORED, 'ignored_at');
    }

    private function transition(HouseInvitation $invitation, string $status, string $timestamp): void
    {
        DB::transaction(function () use ($invitation, $status, $timestamp): void {
            $locked = HouseInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            $this->ensurePending($locked);

            $locked->forceFill([
                'status' => $status,
                $timestamp => now(),
            ])->save();
        });
    }

    private function ensurePending(HouseInvitation $invitation): void
    {
        if ($invitation->status !== HouseInvitation::STATUS_PENDING) {
            throw new ApiException('Invitation is not pending.', 'HOUSE_INVITATION_NOT_PENDING');
        }
    }

    private function pendingInvitation(House $house, User $invitee)
    {
        return HouseInvitation::query()
            ->where('house_id', $house->id)
            ->where('invitee_user_id', $invitee->id)
            ->where('status', HouseInvitation::STATUS_PENDING);
    }
}

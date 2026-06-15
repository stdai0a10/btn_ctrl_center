<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\House;
use App\Models\HouseJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HouseJoinRequestService
{
    public function request(House $house, User $requester): HouseJoinRequest
    {
        if ($house->isMember($requester)) {
            throw new ApiException('User is already a house member.', 'HOUSE_MEMBER_ALREADY_EXISTS');
        }

        if ($this->pendingRequest($house, $requester)->exists()) {
            throw new ApiException('A pending join request already exists.', 'HOUSE_JOIN_REQUEST_ALREADY_PENDING');
        }

        return HouseJoinRequest::query()->create([
            'house_id' => $house->id,
            'requester_user_id' => $requester->id,
            'status' => HouseJoinRequest::STATUS_PENDING,
        ])->load(['house', 'requester']);
    }

    public function cancel(HouseJoinRequest $request, User $requester): void
    {
        if ($request->requester_user_id !== $requester->id) {
            throw new ApiException('This join request belongs to another user.', 'HOUSE_JOIN_REQUEST_FORBIDDEN', 403);
        }

        $this->transition($request, HouseJoinRequest::STATUS_CANCELLED, 'cancelled_at');
    }

    public function accept(HouseJoinRequest $request, User $owner): void
    {
        DB::transaction(function () use ($request, $owner): void {
            $locked = HouseJoinRequest::query()->lockForUpdate()->findOrFail($request->id);

            $this->ensurePending($locked);

            $house = House::query()->lockForUpdate()->findOrFail($locked->house_id);

            if (! $house->isOwner($owner)) {
                throw new ApiException('Only house owners can accept join requests.', 'HOUSE_OWNER_REQUIRED', 403);
            }

            if ($house->isMember($locked->requester)) {
                throw new ApiException('User is already a house member.', 'HOUSE_MEMBER_ALREADY_EXISTS');
            }

            $house->members()->attach($locked->requester_user_id, [
                'role' => House::ROLE_RESIDENT,
                'joined_at' => now(),
            ]);

            $locked->forceFill([
                'status' => HouseJoinRequest::STATUS_ACCEPTED,
                'approved_by_user_id' => $owner->id,
                'accepted_at' => now(),
            ])->save();
        });
    }

    public function ignore(HouseJoinRequest $request, User $owner): void
    {
        if (! $request->house->isOwner($owner)) {
            throw new ApiException('Only house owners can ignore join requests.', 'HOUSE_OWNER_REQUIRED', 403);
        }

        $this->transition($request, HouseJoinRequest::STATUS_IGNORED, 'ignored_at');
    }

    private function transition(HouseJoinRequest $request, string $status, string $timestamp): void
    {
        DB::transaction(function () use ($request, $status, $timestamp): void {
            $locked = HouseJoinRequest::query()->lockForUpdate()->findOrFail($request->id);

            $this->ensurePending($locked);

            $locked->forceFill([
                'status' => $status,
                $timestamp => now(),
            ])->save();
        });
    }

    private function ensurePending(HouseJoinRequest $request): void
    {
        if ($request->status !== HouseJoinRequest::STATUS_PENDING) {
            throw new ApiException('Join request is not pending.', 'HOUSE_JOIN_REQUEST_NOT_PENDING');
        }
    }

    private function pendingRequest(House $house, User $requester)
    {
        return HouseJoinRequest::query()
            ->where('house_id', $house->id)
            ->where('requester_user_id', $requester->id)
            ->where('status', HouseJoinRequest::STATUS_PENDING);
    }
}

<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Room;
use App\Models\RoomJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoomJoinRequestService
{
    public function request(Room $room, User $requester): RoomJoinRequest
    {
        return DB::transaction(function () use ($room, $requester): RoomJoinRequest {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->isMember($requester)) {
                throw new ApiException('User is already a room member.', 'ROOM_MEMBER_ALREADY_EXISTS');
            }

            if ($this->activeRequest($room, $requester)->lockForUpdate()->exists()) {
                throw new ApiException('A pending join request already exists.', 'ROOM_JOIN_REQUEST_ALREADY_PENDING');
            }

            return RoomJoinRequest::query()->create([
                'room_id' => $room->id,
                'requester_user_id' => $requester->id,
                'status' => RoomJoinRequest::STATUS_PENDING,
            ])->load(['room', 'requester']);
        });
    }

    public function cancel(RoomJoinRequest $request, User $requester): void
    {
        if ($request->requester_user_id !== $requester->id) {
            throw new ApiException('This join request belongs to another user.', 'ROOM_JOIN_REQUEST_FORBIDDEN', 403);
        }

        DB::transaction(function () use ($request): void {
            $locked = RoomJoinRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! in_array($locked->status, [
                RoomJoinRequest::STATUS_PENDING,
                RoomJoinRequest::STATUS_IGNORED,
            ], true)) {
                throw new ApiException('Join request cannot be cancelled.', 'ROOM_JOIN_REQUEST_NOT_ACTIVE');
            }

            $locked->forceFill([
                'status' => RoomJoinRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'ignored_at' => null,
            ])->save();
        });
    }

    public function accept(RoomJoinRequest $request, User $owner): void
    {
        DB::transaction(function () use ($request, $owner): void {
            $locked = RoomJoinRequest::query()->lockForUpdate()->findOrFail($request->id);

            $this->ensurePending($locked);

            $room = Room::query()->lockForUpdate()->findOrFail($locked->room_id);

            if (! $room->isOwner($owner)) {
                throw new ApiException('Only room owners can accept join requests.', 'ROOM_OWNER_REQUIRED', 403);
            }

            if ($room->isMember($locked->requester)) {
                throw new ApiException('User is already a room member.', 'ROOM_MEMBER_ALREADY_EXISTS');
            }

            $room->members()->attach($locked->requester_user_id, [
                'role' => Room::ROLE_RESIDENT,
                'joined_at' => now(),
            ]);

            $locked->forceFill([
                'status' => RoomJoinRequest::STATUS_ACCEPTED,
                'approved_by_user_id' => $owner->id,
                'accepted_at' => now(),
            ])->save();
        });
    }

    public function ignore(RoomJoinRequest $request, User $owner): void
    {
        if (! $request->room->isOwner($owner)) {
            throw new ApiException('Only room owners can ignore join requests.', 'ROOM_OWNER_REQUIRED', 403);
        }

        $this->transition($request, RoomJoinRequest::STATUS_IGNORED, 'ignored_at');
    }

    public function restore(RoomJoinRequest $request, User $owner): void
    {
        DB::transaction(function () use ($request, $owner): void {
            $locked = RoomJoinRequest::query()
                ->with('room')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if (! $locked->room->isOwner($owner)) {
                throw new ApiException('Only room owners can restore join requests.', 'ROOM_OWNER_REQUIRED', 403);
            }

            if ($locked->status !== RoomJoinRequest::STATUS_IGNORED) {
                throw new ApiException('Join request is not ignored.', 'ROOM_JOIN_REQUEST_NOT_IGNORED');
            }

            $locked->forceFill([
                'status' => RoomJoinRequest::STATUS_PENDING,
                'ignored_at' => null,
            ])->save();
        });
    }

    private function transition(RoomJoinRequest $request, string $status, string $timestamp): void
    {
        DB::transaction(function () use ($request, $status, $timestamp): void {
            $locked = RoomJoinRequest::query()->lockForUpdate()->findOrFail($request->id);

            $this->ensurePending($locked);

            $locked->forceFill([
                'status' => $status,
                $timestamp => now(),
            ])->save();
        });
    }

    private function ensurePending(RoomJoinRequest $request): void
    {
        if ($request->status !== RoomJoinRequest::STATUS_PENDING) {
            throw new ApiException('Join request is not pending.', 'ROOM_JOIN_REQUEST_NOT_PENDING');
        }
    }

    private function activeRequest(Room $room, User $requester)
    {
        return RoomJoinRequest::query()
            ->where('room_id', $room->id)
            ->where('requester_user_id', $requester->id)
            ->whereIn('status', [
                RoomJoinRequest::STATUS_PENDING,
                RoomJoinRequest::STATUS_IGNORED,
            ]);
    }
}

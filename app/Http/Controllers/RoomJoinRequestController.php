<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Room;
use App\Models\RoomJoinRequest;
use App\Models\User;
use App\Services\RoomJoinRequestService;
use Illuminate\Http\Request;

class RoomJoinRequestController extends ApiController
{
    public function __construct(private readonly RoomJoinRequestService $joinRequests)
    {
    }

    public function index(Request $request)
    {
        $sent = RoomJoinRequest::query()
            ->with(['room', 'requester', 'approver'])
            ->where('requester_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (RoomJoinRequest $joinRequest): array => $this->payload($joinRequest));

        $ownedRoomIds = $request->user()->rooms()
            ->wherePivot('role', Room::ROLE_OWNER)
            ->pluck('rooms.id');

        $received = RoomJoinRequest::query()
            ->with(['room', 'requester', 'approver'])
            ->whereIn('room_id', $ownedRoomIds)
            ->latest()
            ->get()
            ->map(fn (RoomJoinRequest $joinRequest): array => $this->payload($joinRequest));

        return $this->response([
            'sent' => $sent,
            'received' => $received,
        ]);
    }

    public function store(Request $request, Room $room)
    {
        $joinRequest = $this->joinRequests->request($room, $request->user());

        return $this->response($this->payload($joinRequest), '加入申請已送出。', 201);
    }

    public function accept(Request $request, RoomJoinRequest $joinRequest)
    {
        $this->joinRequests->accept($joinRequest, $request->user());

        return $this->response(null, '加入申請已接受。');
    }

    public function ignore(Request $request, RoomJoinRequest $joinRequest)
    {
        $joinRequest->loadMissing('room');

        if (! $joinRequest->room->isOwner($request->user())) {
            throw new ApiException('Only room owners can ignore join requests.', 'ROOM_OWNER_REQUIRED', 403);
        }

        $this->joinRequests->ignore($joinRequest, $request->user());

        return $this->response(null, '加入申請已忽略。');
    }

    public function cancel(Request $request, RoomJoinRequest $joinRequest)
    {
        $this->joinRequests->cancel($joinRequest, $request->user());

        return $this->response(null, '加入申請已取消。');
    }

    private function payload(RoomJoinRequest $joinRequest): array
    {
        $joinRequest->loadMissing(['room', 'requester', 'approver']);

        return [
            'id' => $joinRequest->id,
            'status' => $joinRequest->status,
            'room' => [
                'public_id' => $joinRequest->room->public_id,
                'name' => $joinRequest->room->name,
            ],
            'requester' => $this->userPayload($joinRequest->requester),
            'approver' => $joinRequest->approver ? $this->userPayload($joinRequest->approver) : null,
            'created_at' => $joinRequest->created_at?->toISOString(),
            'accepted_at' => $joinRequest->accepted_at?->toISOString(),
            'ignored_at' => $joinRequest->ignored_at?->toISOString(),
            'cancelled_at' => $joinRequest->cancelled_at?->toISOString(),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
        ];
    }
}

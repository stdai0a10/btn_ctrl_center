<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Room;
use App\Models\RoomJoinRequest;
use App\Models\User;
use App\Services\RoomJoinRequestService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomJoinRequestController extends ApiController
{
    public function __construct(private readonly RoomJoinRequestService $joinRequests) {}

    #[OA\Get(
        path: '/api/room-join-requests',
        operationId: 'roomJoinRequestsIndex',
        summary: 'List sent and received room join requests',
        security: [['sessionCookie' => []]],
        tags: ['Room Join Requests'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $sent = RoomJoinRequest::query()
            ->with(['room', 'requester', 'approver'])
            ->where('requester_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (RoomJoinRequest $joinRequest): array => $this->payload($joinRequest, true));

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

    #[OA\Post(
        path: '/api/rooms/{room}/join-requests',
        operationId: 'roomJoinRequestsStore',
        summary: 'Request to join a room',
        security: [['sessionCookie' => []]],
        tags: ['Room Join Requests'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoom')],
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, Room $room)
    {
        $joinRequest = $this->joinRequests->request($room, $request->user());

        return $this->response($this->payload($joinRequest), '加入申請已送出。', 201);
    }

    #[OA\Post(
        path: '/api/room-join-requests/{joinRequest}/accept',
        operationId: 'roomJoinRequestsAccept',
        summary: 'Accept a room join request',
        security: [['sessionCookie' => []]],
        tags: ['Room Join Requests'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathJoinRequest')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function accept(Request $request, RoomJoinRequest $joinRequest)
    {
        $this->joinRequests->accept($joinRequest, $request->user());

        return $this->response(null, '加入申請已接受。');
    }

    #[OA\Post(
        path: '/api/room-join-requests/{joinRequest}/ignore',
        operationId: 'roomJoinRequestsIgnore',
        summary: 'Ignore a room join request',
        security: [['sessionCookie' => []]],
        tags: ['Room Join Requests'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathJoinRequest')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function ignore(Request $request, RoomJoinRequest $joinRequest)
    {
        $joinRequest->loadMissing('room');

        if (! $joinRequest->room->isOwner($request->user())) {
            throw new ApiException('Only room owners can ignore join requests.', 'ROOM_OWNER_REQUIRED', 403);
        }

        $this->joinRequests->ignore($joinRequest, $request->user());

        return $this->response(null, '加入申請已忽略。');
    }

    #[OA\Post(
        path: '/api/room-join-requests/{joinRequest}/cancel',
        operationId: 'roomJoinRequestsCancel',
        summary: 'Cancel a room join request',
        security: [['sessionCookie' => []]],
        tags: ['Room Join Requests'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathJoinRequest')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function cancel(Request $request, RoomJoinRequest $joinRequest)
    {
        $this->joinRequests->cancel($joinRequest, $request->user());

        return $this->response(null, '加入申請已取消。');
    }

    #[OA\Post(
        path: '/api/room-join-requests/{joinRequest}/restore',
        operationId: 'roomJoinRequestsRestore',
        summary: 'Restore an ignored room join request',
        security: [['sessionCookie' => []]],
        tags: ['Room Join Requests'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathJoinRequest')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function restore(Request $request, RoomJoinRequest $joinRequest)
    {
        $this->joinRequests->restore($joinRequest, $request->user());

        return $this->response(null, '加入申請已恢復為待決定。');
    }

    private function payload(RoomJoinRequest $joinRequest, bool $maskIgnored = false): array
    {
        $joinRequest->loadMissing(['room', 'requester', 'approver']);
        $isMaskedIgnored = $maskIgnored && $joinRequest->status === RoomJoinRequest::STATUS_IGNORED;

        return [
            'id' => $joinRequest->id,
            'status' => $isMaskedIgnored ? RoomJoinRequest::STATUS_PENDING : $joinRequest->status,
            'room' => [
                'public_id' => $joinRequest->room->public_id,
                'name' => $joinRequest->room->name,
            ],
            'requester' => $this->userPayload($joinRequest->requester),
            'approver' => $joinRequest->approver ? $this->userPayload($joinRequest->approver) : null,
            'created_at' => $joinRequest->created_at?->toISOString(),
            'accepted_at' => $joinRequest->accepted_at?->toISOString(),
            'ignored_at' => $isMaskedIgnored ? null : $joinRequest->ignored_at?->toISOString(),
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

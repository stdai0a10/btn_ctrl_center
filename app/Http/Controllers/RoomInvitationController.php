<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Room;
use App\Models\RoomInvitation;
use App\Models\User;
use App\Services\RoomInvitationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomInvitationController extends ApiController
{
    public function __construct(private readonly RoomInvitationService $invitations) {}

    #[OA\Get(
        path: '/api/room-invitations',
        operationId: 'roomInvitationsIndex',
        summary: 'List received and sent room invitations',
        security: [['sessionCookie' => []]],
        tags: ['Room Invitations'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $received = RoomInvitation::query()
            ->with(['room', 'inviter'])
            ->where('invitee_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (RoomInvitation $invitation): array => $this->payload($invitation));

        $ownedRoomIds = $request->user()->rooms()
            ->wherePivot('role', Room::ROLE_OWNER)
            ->pluck('rooms.id');

        $sent = RoomInvitation::query()
            ->with(['room', 'inviter', 'invitee'])
            ->whereIn('room_id', $ownedRoomIds)
            ->latest()
            ->get()
            ->map(fn (RoomInvitation $invitation): array => $this->payload($invitation));

        return $this->response([
            'received' => $received,
            'sent' => $sent,
        ]);
    }

    #[OA\Post(
        path: '/api/rooms/{room}/invitations',
        operationId: 'roomInvitationsStore',
        summary: 'Invite a user to a room',
        security: [['sessionCookie' => []]],
        tags: ['Room Invitations'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoom')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RoomInvitationRequest')),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, Room $room)
    {
        $this->authorize('invite', $room);

        $validated = $request->validate([
            'invitee_public_id' => ['required', 'string', 'exists:users,public_id'],
        ]);

        $invitee = User::query()->where('public_id', $validated['invitee_public_id'])->firstOrFail();
        $invitation = $this->invitations->invite($room, $request->user(), $invitee);

        return $this->response($this->payload($invitation), '邀請已送出。', 201);
    }

    #[OA\Post(
        path: '/api/room-invitations/{invitation}/accept',
        operationId: 'roomInvitationsAccept',
        summary: 'Accept a room invitation',
        security: [['sessionCookie' => []]],
        tags: ['Room Invitations'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathInvitation')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function accept(Request $request, RoomInvitation $invitation)
    {
        $this->invitations->accept($invitation, $request->user());

        return $this->response(null, '邀請已接受。');
    }

    #[OA\Post(
        path: '/api/room-invitations/{invitation}/ignore',
        operationId: 'roomInvitationsIgnore',
        summary: 'Ignore a room invitation',
        security: [['sessionCookie' => []]],
        tags: ['Room Invitations'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathInvitation')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function ignore(Request $request, RoomInvitation $invitation)
    {
        $this->invitations->ignore($invitation, $request->user());

        return $this->response(null, '邀請已忽略。');
    }

    #[OA\Post(
        path: '/api/room-invitations/{invitation}/cancel',
        operationId: 'roomInvitationsCancel',
        summary: 'Cancel a room invitation',
        security: [['sessionCookie' => []]],
        tags: ['Room Invitations'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathInvitation')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function cancel(Request $request, RoomInvitation $invitation)
    {
        $invitation->loadMissing('room');

        if (! $invitation->room->isOwner($request->user())) {
            throw new ApiException('Only room owners can cancel invitations.', 'ROOM_OWNER_REQUIRED', 403);
        }

        $this->invitations->cancel($invitation);

        return $this->response(null, '邀請已取消。');
    }

    private function payload(RoomInvitation $invitation): array
    {
        $invitation->loadMissing(['room', 'inviter', 'invitee']);

        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'room' => [
                'public_id' => $invitation->room->public_id,
                'name' => $invitation->room->name,
            ],
            'inviter' => $this->userPayload($invitation->inviter),
            'invitee' => $this->userPayload($invitation->invitee),
            'created_at' => $invitation->created_at?->toISOString(),
            'accepted_at' => $invitation->accepted_at?->toISOString(),
            'ignored_at' => $invitation->ignored_at?->toISOString(),
            'cancelled_at' => $invitation->cancelled_at?->toISOString(),
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

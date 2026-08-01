<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomJoinRequest;
use App\Services\RoomService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomController extends ApiController
{
    public function __construct(private readonly RoomService $rooms) {}

    #[OA\Get(
        path: '/api/rooms',
        operationId: 'roomsIndex',
        summary: 'List rooms for the authenticated user',
        security: [['sessionCookie' => []]],
        tags: ['Rooms'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $this->authorize('viewAny', Room::class);

        $rooms = $request->user()
            ->rooms()
            ->withCount('members')
            ->latest('rooms.created_at')
            ->get()
            ->map(fn (Room $room): array => $this->payload($room, $request));

        return $this->response($rooms);
    }

    #[OA\Post(
        path: '/api/rooms',
        operationId: 'roomsStore',
        summary: 'Create a room',
        security: [['sessionCookie' => []]],
        tags: ['Rooms'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RoomRequest')),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request)
    {
        $this->authorize('create', Room::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $room = $this->rooms->create($request->user(), $validated['name']);

        return $this->response($this->payload($room, $request), '房間已建立。', 201);
    }

    #[OA\Get(
        path: '/api/rooms/{room}',
        operationId: 'roomsShow',
        summary: 'Get a room',
        security: [['sessionCookie' => []]],
        tags: ['Rooms'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoom')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request, Room $room)
    {
        if (! $room->isMember($request->user())) {
            $joinRequest = RoomJoinRequest::query()
                ->where('room_id', $room->id)
                ->where('requester_user_id', $request->user()->id)
                ->latest()
                ->first();

            return $this->response([
                'public_id' => $room->public_id,
                'name' => $room->name,
                'role' => null,
                'can_access' => false,
                'join_request' => $joinRequest ? [
                    'id' => $joinRequest->id,
                    'status' => $joinRequest->status === RoomJoinRequest::STATUS_IGNORED
                        ? RoomJoinRequest::STATUS_PENDING
                        : $joinRequest->status,
                    'created_at' => $joinRequest->created_at?->toISOString(),
                ] : null,
            ]);
        }

        return $this->response($this->payload($room->load(['members.primaryEmail']), $request, true));
    }

    #[OA\Put(
        path: '/api/rooms/{room}',
        operationId: 'roomsReplace',
        summary: 'Replace room details',
        security: [['sessionCookie' => []]],
        tags: ['Rooms'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoom')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RoomRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    #[OA\Patch(
        path: '/api/rooms/{room}',
        operationId: 'roomsUpdate',
        summary: 'Update room details',
        security: [['sessionCookie' => []]],
        tags: ['Rooms'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoom')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RoomRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function update(Request $request, Room $room)
    {
        $this->authorize('update', $room);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $room = $this->rooms->update($room, $validated['name']);

        return $this->response($this->payload($room, $request), '房間已更新。');
    }

    #[OA\Delete(
        path: '/api/rooms/{room}',
        operationId: 'roomsDestroy',
        summary: 'Delete a room',
        security: [['sessionCookie' => []]],
        tags: ['Rooms'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoom')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function destroy(Request $request, Room $room)
    {
        $this->authorize('delete', $room);

        $this->rooms->delete($room);

        return $this->response(null, '房間已刪除。');
    }

    private function payload(Room $room, Request $request, bool $includeMembers = false): array
    {
        $room->loadMissing('members');

        $payload = [
            'id' => $room->id,
            'public_id' => $room->public_id,
            'name' => $room->name,
            'role' => $room->roleFor($request->user()),
            'can_access' => true,
            'members_count' => $room->members_count ?? $room->members()->count(),
            'created_at' => $room->created_at?->toISOString(),
            'updated_at' => $room->updated_at?->toISOString(),
        ];

        if ($includeMembers) {
            $payload['members'] = $room->members
                ->sortBy('name')
                ->values()
                ->map(fn ($member): array => [
                    'id' => $member->id,
                    'public_id' => $member->public_id,
                    'name' => $member->name,
                    'display_name' => $member->displayName(),
                    'email' => $member->primaryEmail?->email,
                    'role' => $member->pivot->role,
                    'joined_at' => $member->pivot->joined_at,
                ])
                ->all();
        }

        return $payload;
    }
}

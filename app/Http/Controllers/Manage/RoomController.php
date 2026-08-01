<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomController extends ApiController
{
    #[OA\Get(
        path: '/manage/api/rooms',
        operationId: 'manageRoomsIndex',
        summary: 'List rooms',
        security: [['sessionCookie' => []]],
        tags: ['Manage Rooms'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/QuerySearch'),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'deleted'])),
            new OA\Parameter(ref: '#/components/parameters/QueryPage'),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,deleted'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $rooms = Room::query()
            ->withTrashed()
            ->with(['creator.primaryEmail'])
            ->withCount(['members', 'owners'])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('public_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('creator', function (Builder $query) use ($search): void {
                            $query->where('public_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereHas('primaryEmail', fn (Builder $query) => $query->where('email', 'like', "%{$search}%"));
                        });
                });
            })
            ->when(($validated['status'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('deleted_at'))
            ->when(($validated['status'] ?? null) === 'deleted', fn (Builder $query) => $query->onlyTrashed())
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $rooms->getCollection()->transform(fn (Room $room): array => $this->roomPayload($room));

        return $this->response([
            'items' => $rooms->items(),
            'pagination' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
                'from' => $rooms->firstItem(),
                'to' => $rooms->lastItem(),
            ],
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    #[OA\Get(
        path: '/manage/api/rooms/{room_public_id}',
        operationId: 'manageRoomsShow',
        summary: 'Get a room',
        security: [['sessionCookie' => []]],
        tags: ['Manage Rooms'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoomPublicId')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(string $roomPublicId)
    {
        $room = $this->findRoom($roomPublicId);

        return $this->response([
            ...$this->roomPayload($room),
            'members' => $this->membersPayload($room),
        ]);
    }

    #[OA\Get(
        path: '/manage/api/rooms/{room_public_id}/users',
        operationId: 'manageRoomsUsers',
        summary: 'List users in a room',
        security: [['sessionCookie' => []]],
        tags: ['Manage Rooms'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathRoomPublicId')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function users(string $roomPublicId)
    {
        return $this->response($this->membersPayload($this->findRoom($roomPublicId)));
    }

    private function findRoom(string $roomPublicId): Room
    {
        return Room::query()
            ->withTrashed()
            ->with(['creator.primaryEmail'])
            ->withCount(['members', 'owners'])
            ->where('public_id', $roomPublicId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function roomPayload(Room $room): array
    {
        $room->loadMissing('creator.primaryEmail');

        return [
            'id' => $room->id,
            'public_id' => $room->public_id,
            'name' => $room->name,
            'creator' => $room->creator ? [
                'public_id' => $room->creator->public_id,
                'display_name' => $room->creator->displayName(),
                'email' => $room->creator->primaryEmail?->email,
            ] : null,
            'created_at' => $room->created_at?->toISOString(),
            'updated_at' => $room->updated_at?->toISOString(),
            'deleted_at' => $room->deleted_at?->toISOString(),
            'members_count' => $room->members_count ?? $room->members()->count(),
            'owners_count' => $room->owners_count ?? $room->owners()->count(),
            'status' => $room->trashed() ? 'deleted' : 'active',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function membersPayload(Room $room): array
    {
        $room->loadMissing(['members.primaryEmail']);

        return $room->members
            ->sortBy(fn ($member) => $member->displayName())
            ->values()
            ->map(fn ($member): array => [
                'public_id' => $member->public_id,
                'display_name' => $member->displayName(),
                'email' => $member->primaryEmail?->email,
                'role' => $member->pivot->role,
                'joined_at' => $member->pivot->joined_at,
            ])
            ->all();
    }
}

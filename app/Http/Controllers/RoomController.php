<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\Request;

class RoomController extends ApiController
{
    public function __construct(private readonly RoomService $rooms)
    {
    }

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

    public function store(Request $request)
    {
        $this->authorize('create', Room::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $room = $this->rooms->create($request->user(), $validated['name']);

        return $this->response($this->payload($room, $request), '房間已建立。', 201);
    }

    public function show(Request $request, Room $room)
    {
        $this->authorize('view', $room);

        return $this->response($this->payload($room->load(['members.primaryEmail']), $request, true));
    }

    public function update(Request $request, Room $room)
    {
        $this->authorize('update', $room);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $room = $this->rooms->update($room, $validated['name']);

        return $this->response($this->payload($room, $request), '房間已更新。');
    }

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

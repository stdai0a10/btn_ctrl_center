<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Services\RoomMemberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomMemberController extends ApiController
{
    public function __construct(private readonly RoomMemberService $members)
    {
    }

    public function index(Request $request, Room $room)
    {
        $this->authorize('view', $room);

        $members = $room->members()
            ->with('primaryEmail')
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => $this->payload($member));

        return $this->response($members);
    }

    public function destroy(Request $request, Room $room, User $user)
    {
        $this->authorize('manageMembers', $room);

        $this->members->removeMember($room, $request->user(), $user);

        return $this->response(null, '成員已移除。');
    }

    public function updateRole(Request $request, Room $room, User $user)
    {
        $this->authorize('manageMembers', $room);

        $validated = $request->validate([
            'role' => ['required', Rule::in([Room::ROLE_OWNER, Room::ROLE_RESIDENT])],
        ]);

        $this->members->changeRole($room, $request->user(), $user, $validated['role']);

        return $this->response(null, '成員身份已更新。');
    }

    public function leave(Request $request, Room $room)
    {
        $this->authorize('view', $room);

        $deleted = $this->members->leave($room, $request->user());

        return $this->response([
            'room_deleted' => $deleted,
        ], $deleted ? '已退出並刪除房間。' : '已退出房間。');
    }

    private function payload(User $member): array
    {
        return [
            'id' => $member->id,
            'public_id' => $member->public_id,
            'name' => $member->name,
            'display_name' => $member->displayName(),
            'email' => $member->primaryEmail?->email,
            'role' => $member->pivot->role,
            'joined_at' => $member->pivot->joined_at,
        ];
    }
}

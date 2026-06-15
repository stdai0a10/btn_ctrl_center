<?php

namespace App\Http\Controllers;

use App\Models\House;
use App\Models\User;
use App\Services\HouseMemberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseMemberController extends ApiController
{
    public function __construct(private readonly HouseMemberService $members)
    {
    }

    public function index(Request $request, House $house)
    {
        $this->authorize('view', $house);

        $members = $house->members()
            ->with('primaryEmail')
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => $this->payload($member));

        return $this->response($members);
    }

    public function destroy(Request $request, House $house, User $user)
    {
        $this->authorize('manageMembers', $house);

        $this->members->removeMember($house, $request->user(), $user);

        return $this->response(null, '成員已移除。');
    }

    public function updateRole(Request $request, House $house, User $user)
    {
        $this->authorize('manageMembers', $house);

        $validated = $request->validate([
            'role' => ['required', Rule::in([House::ROLE_OWNER, House::ROLE_RESIDENT])],
        ]);

        $this->members->changeRole($house, $request->user(), $user, $validated['role']);

        return $this->response(null, '成員身份已更新。');
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

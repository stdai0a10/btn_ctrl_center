<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\House;
use App\Models\HouseInvitation;
use App\Models\User;
use App\Services\HouseInvitationService;
use Illuminate\Http\Request;

class HouseInvitationController extends ApiController
{
    public function __construct(private readonly HouseInvitationService $invitations)
    {
    }

    public function index(Request $request)
    {
        $received = HouseInvitation::query()
            ->with(['house', 'inviter'])
            ->where('invitee_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (HouseInvitation $invitation): array => $this->payload($invitation));

        $ownedHouseIds = $request->user()->houses()
            ->wherePivot('role', House::ROLE_OWNER)
            ->pluck('houses.id');

        $sent = HouseInvitation::query()
            ->with(['house', 'inviter', 'invitee'])
            ->whereIn('house_id', $ownedHouseIds)
            ->latest()
            ->get()
            ->map(fn (HouseInvitation $invitation): array => $this->payload($invitation));

        return $this->response([
            'received' => $received,
            'sent' => $sent,
        ]);
    }

    public function store(Request $request, House $house)
    {
        $this->authorize('invite', $house);

        $validated = $request->validate([
            'invitee_public_id' => ['required', 'string', 'exists:users,public_id'],
        ]);

        $invitee = User::query()->where('public_id', $validated['invitee_public_id'])->firstOrFail();
        $invitation = $this->invitations->invite($house, $request->user(), $invitee);

        return $this->response($this->payload($invitation), '邀請已送出。', 201);
    }

    public function accept(Request $request, HouseInvitation $invitation)
    {
        $this->invitations->accept($invitation, $request->user());

        return $this->response(null, '邀請已接受。');
    }

    public function ignore(Request $request, HouseInvitation $invitation)
    {
        $this->invitations->ignore($invitation, $request->user());

        return $this->response(null, '邀請已忽略。');
    }

    public function cancel(Request $request, HouseInvitation $invitation)
    {
        $invitation->loadMissing('house');

        if (! $invitation->house->isOwner($request->user())) {
            throw new ApiException('Only house owners can cancel invitations.', 'HOUSE_OWNER_REQUIRED', 403);
        }

        $this->invitations->cancel($invitation);

        return $this->response(null, '邀請已取消。');
    }

    private function payload(HouseInvitation $invitation): array
    {
        $invitation->loadMissing(['house', 'inviter', 'invitee']);

        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'house' => [
                'public_id' => $invitation->house->public_id,
                'name' => $invitation->house->name,
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

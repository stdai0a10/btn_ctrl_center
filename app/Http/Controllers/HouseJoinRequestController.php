<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\House;
use App\Models\HouseJoinRequest;
use App\Models\User;
use App\Services\HouseJoinRequestService;
use Illuminate\Http\Request;

class HouseJoinRequestController extends ApiController
{
    public function __construct(private readonly HouseJoinRequestService $joinRequests)
    {
    }

    public function index(Request $request)
    {
        $sent = HouseJoinRequest::query()
            ->with(['house', 'requester', 'approver'])
            ->where('requester_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (HouseJoinRequest $joinRequest): array => $this->payload($joinRequest));

        $ownedHouseIds = $request->user()->houses()
            ->wherePivot('role', House::ROLE_OWNER)
            ->pluck('houses.id');

        $received = HouseJoinRequest::query()
            ->with(['house', 'requester', 'approver'])
            ->whereIn('house_id', $ownedHouseIds)
            ->latest()
            ->get()
            ->map(fn (HouseJoinRequest $joinRequest): array => $this->payload($joinRequest));

        return $this->response([
            'sent' => $sent,
            'received' => $received,
        ]);
    }

    public function store(Request $request, House $house)
    {
        $joinRequest = $this->joinRequests->request($house, $request->user());

        return $this->response($this->payload($joinRequest), '加入申請已送出。', 201);
    }

    public function accept(Request $request, HouseJoinRequest $joinRequest)
    {
        $this->joinRequests->accept($joinRequest, $request->user());

        return $this->response(null, '加入申請已接受。');
    }

    public function ignore(Request $request, HouseJoinRequest $joinRequest)
    {
        $joinRequest->loadMissing('house');

        if (! $joinRequest->house->isOwner($request->user())) {
            throw new ApiException('Only house owners can ignore join requests.', 'HOUSE_OWNER_REQUIRED', 403);
        }

        $this->joinRequests->ignore($joinRequest, $request->user());

        return $this->response(null, '加入申請已忽略。');
    }

    public function cancel(Request $request, HouseJoinRequest $joinRequest)
    {
        $this->joinRequests->cancel($joinRequest, $request->user());

        return $this->response(null, '加入申請已取消。');
    }

    private function payload(HouseJoinRequest $joinRequest): array
    {
        $joinRequest->loadMissing(['house', 'requester', 'approver']);

        return [
            'id' => $joinRequest->id,
            'status' => $joinRequest->status,
            'house' => [
                'public_id' => $joinRequest->house->public_id,
                'name' => $joinRequest->house->name,
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

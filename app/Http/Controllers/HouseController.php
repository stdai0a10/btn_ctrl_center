<?php

namespace App\Http\Controllers;

use App\Models\House;
use App\Services\HouseService;
use Illuminate\Http\Request;

class HouseController extends ApiController
{
    public function __construct(private readonly HouseService $houses)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', House::class);

        $houses = $request->user()
            ->houses()
            ->withCount('members')
            ->latest('houses.created_at')
            ->get()
            ->map(fn (House $house): array => $this->payload($house, $request));

        return $this->response($houses);
    }

    public function store(Request $request)
    {
        $this->authorize('create', House::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $house = $this->houses->create($request->user(), $validated['name']);

        return $this->response($this->payload($house, $request), '房屋已建立。', 201);
    }

    public function show(Request $request, House $house)
    {
        $this->authorize('view', $house);

        return $this->response($this->payload($house->load(['members.primaryEmail']), $request, true));
    }

    public function update(Request $request, House $house)
    {
        $this->authorize('update', $house);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $house = $this->houses->update($house, $validated['name']);

        return $this->response($this->payload($house, $request), '房屋已更新。');
    }

    public function destroy(Request $request, House $house)
    {
        $this->authorize('delete', $house);

        $this->houses->delete($house);

        return $this->response(null, '房屋已刪除。');
    }

    private function payload(House $house, Request $request, bool $includeMembers = false): array
    {
        $house->loadMissing('members');

        $payload = [
            'id' => $house->id,
            'public_id' => $house->public_id,
            'name' => $house->name,
            'role' => $house->roleFor($request->user()),
            'members_count' => $house->members_count ?? $house->members()->count(),
            'created_at' => $house->created_at?->toISOString(),
            'updated_at' => $house->updated_at?->toISOString(),
        ];

        if ($includeMembers) {
            $payload['members'] = $house->members
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

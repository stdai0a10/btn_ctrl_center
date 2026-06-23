<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Auth\AuthAttemptLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $users = User::query()
            ->with(['primaryEmail', 'authProviders'])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('public_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('primaryEmail', fn (Builder $query) => $query->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $users->getCollection()->transform(fn (User $user): array => $this->userPayload($user));

        return $this->response([
            'items' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function show(string $userPublicId)
    {
        $user = $this->findUser($userPublicId);

        return $this->response([
            ...$this->userPayload($user),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
            'roles' => $user->getRoleNames()->values(),
            'rooms' => $this->roomsPayload($user),
        ]);
    }

    public function rooms(string $userPublicId)
    {
        return $this->response($this->roomsPayload($this->findUser($userPublicId)));
    }

    private function findUser(string $userPublicId): User
    {
        return User::query()
            ->with(['primaryEmail', 'authProviders'])
            ->where('public_id', $userPublicId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['primaryEmail', 'authProviders']);

        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
            'email' => $user->primaryEmail?->email,
            'line_bound' => $user->authProviders->contains('provider', 'line'),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'last_login_at' => $this->lastLoginAt($user),
            'status' => $user->status,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roomsPayload(User $user): array
    {
        $user->loadMissing(['rooms' => fn ($query) => $query->with('creator')->latest('rooms.created_at')]);

        return $user->rooms
            ->map(fn ($room): array => [
                'public_id' => $room->public_id,
                'name' => $room->name,
                'role' => $room->pivot->role,
                'joined_at' => $room->pivot->joined_at,
                'created_at' => $room->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function lastLoginAt(User $user): ?string
    {
        $email = $user->primaryEmail?->email;

        if ($email === null) {
            return null;
        }

        return AuthAttemptLog::query()
            ->where('type', 'login')
            ->where('account_key', $email)
            ->where('is_success', true)
            ->latest('created_at')
            ->first()
            ?->created_at
            ?->toISOString();
    }
}

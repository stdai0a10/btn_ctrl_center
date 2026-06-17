<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function me(Request $request)
    {
        return $this->response($this->adminPayload($request));
    }

    public function dashboard(Request $request)
    {
        return $this->response([
            'stats' => [
                'users_count' => User::query()->count(),
                'rooms_count' => Room::query()->count(),
                'users_created_today_count' => User::query()->whereDate('created_at', today())->count(),
                'rooms_created_today_count' => Room::query()->whereDate('created_at', today())->count(),
            ],
            'recent_manage_login_at' => $request->session()->get('manage_authenticated_at'),
            'admin' => $this->adminPayload($request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('primaryEmail');

        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
            'email' => $user->primaryEmail?->email,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}

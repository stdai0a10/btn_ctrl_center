<?php

namespace App\Http\Middleware;

use App\Models\ManageActionLog;
use App\Models\Room;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogManageAction
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user !== null) {
            [$targetType, $targetId, $targetPublicId] = $this->target($request);

            ManageActionLog::query()->create([
                'user_id' => $user->id,
                'action' => $request->route()?->getName() ?? $request->method().' '.$request->path(),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_public_id' => $targetPublicId,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'metadata' => [
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'query' => $request->query(),
                    'status' => $response->getStatusCode(),
                ],
            ]);
        }

        return $response;
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: ?string}
     */
    private function target(Request $request): array
    {
        $userPublicId = $request->route('user_public_id');
        if (is_string($userPublicId)) {
            $user = User::query()->where('public_id', $userPublicId)->first();

            return ['user', $user?->id, $userPublicId];
        }

        $roomPublicId = $request->route('room_public_id');
        if (is_string($roomPublicId)) {
            $room = Room::query()->withTrashed()->where('public_id', $roomPublicId)->first();

            return ['room', $room?->id, $roomPublicId];
        }

        return [null, null, null];
    }
}

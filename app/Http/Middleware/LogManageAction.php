<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\Product;
use App\Models\Room;
use App\Models\User;
use App\Services\Manage\ManageActionLogger;
use App\Support\DeviceSerial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogManageAction
{
    public function __construct(private readonly ManageActionLogger $logger) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        $action = $this->action($request);

        if ($user !== null && $action !== null && $response->isSuccessful()) {
            [$targetType, $targetId, $targetPublicId] = $this->target($request);

            $this->logger->forManageUser(
                request: $request,
                action: $action,
                targetType: $targetType,
                targetId: $targetId,
                targetPublicId: $targetPublicId,
                metadata: [
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'query' => $request->query(),
                    'status' => $response->getStatusCode(),
                ],
            );
        }

        return $response;
    }

    private function action(Request $request): ?string
    {
        return match ($request->route()?->getName()) {
            'manage.api.users.show', 'manage.api.users.rooms' => 'users.detail.view',
            'manage.api.rooms.show', 'manage.api.rooms.users' => 'rooms.detail.view',
            'manage.api.rooms.devices' => 'rooms.devices.view',
            'manage.api.devices.show' => 'devices.detail.view',
            'manage.api.device-runtime.index' => 'device_runtime.view',
            'manage.api.button-jobs.index' => 'button_jobs.view',
            'manage.api.products.show' => 'products.detail.view',
            'manage.api.audit.login-failures' => 'audit.login_failures.view',
            'manage.api.audit.manage-actions' => 'audit.manage_actions.view',
            default => null,
        };
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

        $serialNumber = $request->route('serial_number');
        if (is_string($serialNumber)) {
            $serialNumber = DeviceSerial::normalize($serialNumber);
            $device = Device::query()->where('serial_number', $serialNumber)->first();

            return ['device', $device?->id, $serialNumber];
        }

        $productPublicId = $request->route('product_public_id');
        if (is_string($productPublicId)) {
            $product = Product::query()->where('public_id', $productPublicId)->first();

            return ['product', $product?->id, $productPublicId];
        }

        return [null, null, null];
    }
}

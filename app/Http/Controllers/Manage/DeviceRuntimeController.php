<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Device;
use App\Services\Manage\DeviceRuntimeService;
use App\Support\DeviceSerial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceRuntimeController extends ApiController
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'runner_status' => ['nullable', 'string', 'max:50'],
            'runtime_disabled' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);

        $devices = Device::query()
            ->with(['currentRoom', 'product'])
            ->withCount(['jwtTokens as active_jwt_tokens_count' => fn (Builder $query) => $query->whereNull('revoked_at')])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $normalized = DeviceSerial::normalize($search);
                $query->where(function (Builder $query) use ($search, $normalized): void {
                    $query->where('serial_number', 'like', "%{$normalized}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('product', fn (Builder $query) => $query
                            ->where('model_number', 'like', "%{$normalized}%")
                            ->orWhere('name', 'like', "%{$search}%"))
                        ->orWhereHas('currentRoom', fn (Builder $query) => $query
                            ->where('public_id', 'like', "%{$normalized}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($validated['runner_status'] ?? null, fn (Builder $query, string $status) => $query->where('runner_status', $status))
            ->when(array_key_exists('runtime_disabled', $validated), fn (Builder $query) => $request->boolean('runtime_disabled')
                ? $query->whereNotNull('runner_disabled_at')
                : $query->whereNull('runner_disabled_at'))
            ->orderByDesc('runner_last_seen_at')
            ->orderBy('serial_number')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        $devices->getCollection()->transform(fn (Device $device): array => $this->deviceRuntimePayload($device));

        return $this->response([
            ...$this->paginatedPayload($devices),
            'filters' => $validated,
        ]);
    }

    public function disable(Request $request, DeviceRuntimeService $runtime, string $serialNumber)
    {
        $device = $this->findDevice($serialNumber);

        return $this->response($this->deviceRuntimePayload($runtime->disable($request, $device)), '設備 runtime 已停用。');
    }

    public function enable(Request $request, DeviceRuntimeService $runtime, string $serialNumber)
    {
        $device = $this->findDevice($serialNumber);

        return $this->response($this->deviceRuntimePayload($runtime->enable($request, $device)), '設備 runtime 已啟用。');
    }

    public function revokeTokens(Request $request, DeviceRuntimeService $runtime, string $serialNumber)
    {
        $device = $this->findDevice($serialNumber);

        return $this->response($this->deviceRuntimePayload($runtime->revokeTokens($request, $device)), '設備 JWT 已撤銷。');
    }

    private function findDevice(string $serialNumber): Device
    {
        return Device::query()
            ->with(['currentRoom', 'product'])
            ->where('serial_number', DeviceSerial::normalize($serialNumber))
            ->firstOrFail();
    }

    private function deviceRuntimePayload(Device $device): array
    {
        $device->loadMissing(['currentRoom', 'product']);

        return [
            'serial_number' => $device->serial_number,
            'name' => $device->name,
            'product' => $device->product === null ? null : [
                'public_id' => $device->product->public_id,
                'model_number' => $device->product->model_number,
                'name' => $device->product->name,
            ],
            'room' => $device->currentRoom === null ? null : [
                'public_id' => $device->currentRoom->public_id,
                'name' => $device->currentRoom->name,
                'status' => $device->currentRoom->trashed() ? 'deleted' : 'active',
            ],
            'runtime' => [
                'runner_status' => $device->runner_status,
                'runner_current_job_id' => $device->runner_current_job_id,
                'runner_last_seen_at' => $device->runner_last_seen_at?->toISOString(),
                'runner_registered_at' => $device->runner_registered_at?->toISOString(),
                'runner_disabled_at' => $device->runner_disabled_at?->toISOString(),
            ],
            'tokens' => [
                'long_token_issued_at' => $device->long_token_issued_at?->toISOString(),
                'long_token_expires_at' => $device->long_token_expires_at?->toISOString(),
                'long_token_revoked_at' => $device->long_token_revoked_at?->toISOString(),
                'current_access_expires_at' => $device->current_access_expires_at?->toISOString(),
                'active_token_count' => $device->active_jwt_tokens_count ?? $device->jwtTokens()->whereNull('revoked_at')->count(),
                'token_version' => $device->token_version,
            ],
            'updated_at' => $device->updated_at?->toISOString(),
        ];
    }

    private function paginatedPayload($paginator): array
    {
        return [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Device;
use App\Models\DeviceTransferLog;
use App\Models\Room;
use App\Services\Manage\DeviceCatalogService;
use App\Support\DeviceSerial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class DeviceController extends ApiController
{
    #[OA\Post(
        path: '/manage/api/devices',
        operationId: 'manageDevicesStore',
        summary: 'Create a device catalog record',
        security: [['sessionCookie' => []]],
        tags: ['Manage Devices'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ManageDeviceCreateRequest')),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, DeviceCatalogService $catalog)
    {
        $validated = $request->validate([
            'product_public_id' => ['required', 'string', 'max:20'],
            'serial_number' => ['required', 'string', 'max:100'],
            'secret' => ['required', 'string', 'max:255', 'confirmed'],
        ]);

        $device = $catalog->create(
            $request,
            $validated['product_public_id'],
            $validated['serial_number'],
            $validated['secret'],
        );

        return $this->response($this->devicePayload($device), '設備主檔已建立。', 201);
    }

    #[OA\Get(
        path: '/manage/api/devices',
        operationId: 'manageDevicesIndex',
        summary: 'List devices',
        security: [['sessionCookie' => []]],
        tags: ['Manage Devices'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/QuerySearch'),
            new OA\Parameter(name: 'room_public_id', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 26)),
            new OA\Parameter(name: 'assignment_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['all', 'assigned', 'unassigned'])),
            new OA\Parameter(name: 'locked', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'enabled', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(ref: '#/components/parameters/QueryPage'),
            new OA\Parameter(ref: '#/components/parameters/QueryPerPage'),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'room_public_id' => ['nullable', 'string', 'max:26'],
            'assignment_status' => ['nullable', Rule::in(['all', 'assigned', 'unassigned'])],
            'locked' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);

        $devices = Device::query()
            ->with(['currentRoom', 'product'])
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
            ->when($validated['room_public_id'] ?? null, fn (Builder $query, string $publicId) => $query
                ->whereHas('currentRoom', fn (Builder $query) => $query->where('public_id', DeviceSerial::normalize($publicId))))
            ->when(($validated['assignment_status'] ?? 'all') === 'assigned', fn (Builder $query) => $query->whereNotNull('current_room_id'))
            ->when(($validated['assignment_status'] ?? 'all') === 'unassigned', fn (Builder $query) => $query->whereNull('current_room_id'))
            ->when(array_key_exists('locked', $validated), fn (Builder $query) => $query->where('is_locked', $request->boolean('locked')))
            ->when(array_key_exists('enabled', $validated), fn (Builder $query) => $query->where('is_enabled', $request->boolean('enabled')))
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        $devices->getCollection()->transform(fn (Device $device): array => $this->devicePayload($device));

        return $this->response([
            ...$this->paginatedPayload($devices),
            'filters' => $validated,
        ]);
    }

    #[OA\Get(
        path: '/manage/api/devices/{serial_number}',
        operationId: 'manageDevicesShow',
        summary: 'Get a device and its transfer history',
        security: [['sessionCookie' => []]],
        tags: ['Manage Devices'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PathSerialNumber'),
            new OA\Parameter(name: 'transfers_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request, string $serialNumber)
    {
        $device = Device::query()
            ->with(['currentRoom', 'product.functions'])
            ->where('serial_number', DeviceSerial::normalize($serialNumber))
            ->firstOrFail();

        $transfers = $device->transferLogs()
            ->with(['fromRoom', 'toRoom', 'transferredBy'])
            ->latest('created_at')
            ->paginate(20, ['*'], 'transfers_page', $request->integer('transfers_page', 1));

        $transfers->getCollection()->transform(fn (DeviceTransferLog $log): array => [
            'id' => $log->id,
            'from_room_public_id' => $log->from_room_public_id_snapshot ?? $log->fromRoom?->public_id,
            'to_room_public_id' => $log->to_room_public_id_snapshot ?? $log->toRoom?->public_id,
            'transferred_by_user_public_id' => $log->transferred_by_user_public_id_snapshot ?? $log->transferredBy?->public_id,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return $this->response([
            ...$this->devicePayload($device),
            'transfer_logs' => $this->paginatedPayload($transfers),
        ]);
    }

    #[OA\Get(
        path: '/manage/api/rooms/{room_public_id}/devices',
        operationId: 'manageRoomDevicesIndex',
        summary: 'List devices assigned to a room',
        security: [['sessionCookie' => []]],
        tags: ['Manage Devices'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PathRoomPublicId'),
            new OA\Parameter(ref: '#/components/parameters/QueryPage'),
            new OA\Parameter(ref: '#/components/parameters/QueryPerPage'),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function roomDevices(Request $request, string $roomPublicId)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);

        $room = Room::query()
            ->withTrashed()
            ->where('public_id', DeviceSerial::normalize($roomPublicId))
            ->firstOrFail();

        $devices = $room->devices()
            ->with('product')
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        $devices->getCollection()->transform(fn (Device $device): array => $this->devicePayload($device));

        return $this->response([
            'room' => [
                'public_id' => $room->public_id,
                'name' => $room->name,
                'status' => $room->trashed() ? 'deleted' : 'active',
            ],
            ...$this->paginatedPayload($devices),
        ]);
    }

    private function devicePayload(Device $device): array
    {
        $device->loadMissing(['currentRoom', 'product']);

        return [
            'serial_number' => $device->serial_number,
            'product' => $device->product === null ? null : [
                'public_id' => $device->product->public_id,
                'model_number' => $device->product->model_number,
                'name' => $device->product->name,
                'functions' => $device->relationLoaded('product') && $device->product->relationLoaded('functions')
                    ? $device->product->functions->map(fn ($function): array => [
                        'code' => $function->code,
                        'description' => $function->description,
                    ])->values()->all()
                    : null,
            ],
            'name' => $device->name,
            'is_locked' => $device->is_locked,
            'is_enabled' => $device->is_enabled,
            'assignment_status' => $device->current_room_id === null ? 'unassigned' : 'assigned',
            'room' => $device->currentRoom === null ? null : [
                'public_id' => $device->currentRoom->public_id,
                'name' => $device->currentRoom->name,
                'status' => $device->currentRoom->trashed() ? 'deleted' : 'active',
            ],
            'created_at' => $device->created_at?->toISOString(),
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

<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Room;
use App\Services\DeviceService;
use Illuminate\Http\Request;

class DeviceController extends ApiController
{
    public function __construct(private readonly DeviceService $devices)
    {
    }

    public function index(Request $request, Room $room)
    {
        $this->authorize('view', $room);

        $devices = $room->devices()
            ->with('product')
            ->latest()
            ->get()
            ->map(fn (Device $device): array => $this->payload($device));

        return $this->response($devices);
    }

    public function store(Request $request, Room $room)
    {
        $this->authorize('manageDevices', $room);

        $validated = $request->validate([
            'serial_number' => ['required', 'string', 'max:100'],
            'secret' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'lock' => ['nullable', 'boolean'],
        ]);

        $device = $this->devices->attachToRoom(
            $room,
            $request->user(),
            $validated['serial_number'],
            $validated['secret'],
            $validated['name'] ?? null,
            (bool) ($validated['lock'] ?? false),
        );

        return $this->response($this->payload($device), '設備已加入房間。', 201);
    }

    public function update(Request $request, Room $room, Device $device)
    {
        $this->authorize('manageDevices', $room);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $device = $this->devices->rename($room, $device, $validated['name'] ?? null);

        return $this->response($this->payload($device), '設備已更新。');
    }

    public function destroy(Request $request, Room $room, Device $device)
    {
        $this->authorize('manageDevices', $room);

        $this->devices->removeFromRoom($room, $device);

        return $this->response(null, '設備已移除。');
    }

    public function lock(Request $request, Room $room, Device $device)
    {
        $this->authorize('manageDevices', $room);

        $device = $this->devices->lock($room, $device);

        return $this->response($this->payload($device), '設備已上鎖。');
    }

    public function unlock(Request $request, Room $room, Device $device)
    {
        $this->authorize('manageDevices', $room);

        $device = $this->devices->unlock($room, $device);

        return $this->response($this->payload($device), '設備已解鎖。');
    }

    public function enable(Request $request, Room $room, Device $device)
    {
        $this->authorize('manageDevices', $room);

        $device = $this->devices->enable($room, $device);

        return $this->response($this->payload($device), '設備已啟用。');
    }

    public function disable(Request $request, Room $room, Device $device)
    {
        $this->authorize('manageDevices', $room);

        $device = $this->devices->disable($room, $device);

        return $this->response($this->payload($device), '設備已停用。');
    }

    private function payload(Device $device): array
    {
        $device->loadMissing('product');

        return [
            'id' => $device->id,
            'serial_number' => $device->serial_number,
            'product' => $device->product === null ? null : [
                'model_number' => $device->product->model_number,
                'name' => $device->product->name,
            ],
            'current_room_id' => $device->current_room_id,
            'name' => $device->name,
            'is_locked' => $device->is_locked,
            'is_enabled' => $device->is_enabled,
            'created_at' => $device->created_at?->toISOString(),
            'updated_at' => $device->updated_at?->toISOString(),
        ];
    }
}

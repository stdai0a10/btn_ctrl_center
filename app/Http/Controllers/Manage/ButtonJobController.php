<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\ButtonActionJob;
use App\Services\Manage\DeviceRuntimeService;
use App\Support\DeviceSerial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ButtonJobController extends ApiController
{
    #[OA\Get(
        path: '/manage/api/button-jobs',
        operationId: 'manageButtonJobsIndex',
        summary: 'List button action jobs',
        security: [['sessionCookie' => []]],
        tags: ['Manage Button Jobs'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/QuerySearch'),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 50)),
            new OA\Parameter(name: 'device_serial_number', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 100)),
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
            'status' => ['nullable', 'string', 'max:50'],
            'device_serial_number' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);

        $jobs = ButtonActionJob::query()
            ->with(['user', 'button', 'device.currentRoom', 'productFunction'])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $normalized = DeviceSerial::normalize($search);
                $query->where(function (Builder $query) use ($search, $normalized): void {
                    $query->where('public_id', 'like', "%{$search}%")
                        ->orWhere('request_id', 'like', "%{$search}%")
                        ->orWhereHas('device', fn (Builder $query) => $query
                            ->where('serial_number', 'like', "%{$normalized}%")
                            ->orWhere('name', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn (Builder $query) => $query
                            ->where('public_id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['device_serial_number'] ?? null, fn (Builder $query, string $serialNumber) => $query
                ->whereHas('device', fn (Builder $query) => $query->where('serial_number', DeviceSerial::normalize($serialNumber))))
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        $jobs->getCollection()->transform(fn (ButtonActionJob $job): array => $this->jobPayload($job));

        return $this->response([
            ...$this->paginatedPayload($jobs),
            'filters' => $validated,
        ]);
    }

    #[OA\Post(
        path: '/manage/api/button-jobs/{button_action_job_public_id}/cancel',
        operationId: 'manageButtonJobsCancel',
        summary: 'Cancel a button action job',
        security: [['sessionCookie' => []]],
        tags: ['Manage Button Jobs'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathDeviceJob')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function cancel(Request $request, DeviceRuntimeService $runtime, string $jobPublicId)
    {
        $job = ButtonActionJob::query()
            ->where('public_id', $jobPublicId)
            ->firstOrFail();

        return $this->response($this->jobPayload($runtime->cancelJob($request, $job)), '按鈕任務已取消。');
    }

    private function jobPayload(ButtonActionJob $job): array
    {
        $job->loadMissing(['user', 'button', 'device.currentRoom', 'productFunction']);

        return [
            'public_id' => $job->public_id,
            'status' => $job->status,
            'source' => $job->source,
            'progress' => $job->progress,
            'progress_message' => $job->progress_message,
            'error_code' => $job->error_code,
            'error_message' => $job->error_message,
            'created_at' => $job->created_at?->toISOString(),
            'started_at' => $job->started_at?->toISOString(),
            'finished_at' => $job->finished_at?->toISOString(),
            'lease_expires_at' => $job->lease_expires_at?->toISOString(),
            'front_end_timeout_at' => $job->front_end_timeout_at?->toISOString(),
            'user' => [
                'public_id' => $job->user->public_id,
                'display_name' => $job->user->displayName(),
            ],
            'device' => [
                'serial_number' => $job->device->serial_number,
                'name' => $job->device->name,
                'room' => $job->device->currentRoom === null ? null : [
                    'public_id' => $job->device->currentRoom->public_id,
                    'name' => $job->device->currentRoom->name,
                ],
            ],
            'function' => [
                'code' => $job->productFunction->code,
                'description' => $job->productFunction->description,
            ],
            'button_public_id' => $job->button?->public_id,
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

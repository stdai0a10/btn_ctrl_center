<?php

namespace App\Http\Controllers;

use App\Models\ButtonActionJob;
use App\Models\ButtonPageItem;
use App\Services\Button\ButtonActionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ButtonActionController extends ApiController
{
    #[OA\Post(
        path: '/api/button-actions',
        operationId: 'buttonActionsStore',
        summary: 'Trigger a button action',
        security: [['sessionCookie' => []]],
        tags: ['Button Actions'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ButtonActionRequest')),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, ButtonActionService $actions)
    {
        $validated = $request->validate([
            'button_public_id' => ['required', 'string', 'max:20'],
            'request_id' => ['required', 'string', 'max:100'],
        ]);

        $button = ButtonPageItem::query()
            ->with(['page', 'device.currentRoom', 'device.product', 'productFunction'])
            ->where('public_id', $validated['button_public_id'])
            ->firstOrFail();

        $job = $actions->trigger($request->user(), $button, $validated['request_id']);

        return $this->response(['job' => $this->jobPayload($job)], '按鈕任務已建立。', 201);
    }

    #[OA\Get(
        path: '/api/button-actions/current',
        operationId: 'buttonActionsCurrent',
        summary: 'Get the current button action job',
        security: [['sessionCookie' => []]],
        tags: ['Button Actions'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function current(Request $request)
    {
        $job = ButtonActionJob::query()
            ->with(['button', 'device', 'productFunction'])
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [ButtonActionJob::STATUS_QUEUED, ButtonActionJob::STATUS_RUNNING])
            ->latest('created_at')
            ->first();

        return $this->response($job === null ? null : ['job' => $this->jobPayload($job)]);
    }

    #[OA\Get(
        path: '/api/button-actions/{buttonActionJob}',
        operationId: 'buttonActionsShow',
        summary: 'Get a button action job',
        security: [['sessionCookie' => []]],
        tags: ['Button Actions'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathButtonActionJob')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request, string $buttonActionJobPublicId)
    {
        $job = ButtonActionJob::query()
            ->with(['button', 'device', 'productFunction'])
            ->where('user_id', $request->user()->id)
            ->where('public_id', $buttonActionJobPublicId)
            ->firstOrFail();

        return $this->response(['job' => $this->jobPayload($job)]);
    }

    private function jobPayload(ButtonActionJob $job): array
    {
        $job->loadMissing(['button', 'device', 'productFunction']);

        return [
            'public_id' => $job->public_id,
            'status' => $job->status,
            'progress' => $job->progress,
            'progress_message' => $job->progress_message,
            'error_code' => $job->error_code,
            'error_message' => $job->error_message,
            'result' => $job->result,
            'expires_at' => $job->front_end_timeout_at?->toISOString(),
            'created_at' => $job->created_at?->toISOString(),
            'started_at' => $job->started_at?->toISOString(),
            'finished_at' => $job->finished_at?->toISOString(),
            'button_public_id' => $job->button?->public_id,
            'device' => [
                'serial_number' => $job->device->serial_number,
                'name' => $job->device->name,
            ],
            'function' => [
                'code' => $job->productFunction->code,
                'description' => $job->productFunction->description,
            ],
        ];
    }
}

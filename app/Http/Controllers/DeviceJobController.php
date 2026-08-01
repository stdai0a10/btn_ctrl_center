<?php

namespace App\Http\Controllers;

use App\Models\ButtonActionJob;
use App\Services\DeviceRuntime\DeviceJobService;
use App\Services\DeviceRuntime\DeviceTokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceJobController extends ApiController
{
    public function poll(Request $request, DeviceTokenService $tokens, DeviceJobService $jobs, string $serialNumber)
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'current_job_id' => ['nullable', 'string', 'max:20'],
        ]);

        $device = $tokens->authenticateAccessToken($serialNumber, $request->bearerToken() ?: '', 'device:poll');
        $job = $jobs->poll($device);

        if ($job === null) {
            return response()->noContent();
        }

        return $this->response($this->deviceJobPayload($job));
    }

    public function progress(Request $request, DeviceTokenService $tokens, DeviceJobService $jobs, string $buttonActionJobPublicId)
    {
        $validated = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'message' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $job = ButtonActionJob::query()->with('device')->where('public_id', $buttonActionJobPublicId)->firstOrFail();
        $device = $tokens->authenticateAccessToken($job->device->serial_number, $request->bearerToken() ?: '', 'device:progress');
        $job = $jobs->progress($device, $job, (int) $validated['progress'], $validated['message'] ?? null);

        return $this->response(['job' => $this->statusPayload($job)]);
    }

    public function complete(Request $request, DeviceTokenService $tokens, DeviceJobService $jobs, string $buttonActionJobPublicId)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ButtonActionJob::STATUS_SUCCEEDED, ButtonActionJob::STATUS_FAILED])],
            'result' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $job = ButtonActionJob::query()->with('device')->where('public_id', $buttonActionJobPublicId)->firstOrFail();
        $device = $tokens->authenticateAccessToken($job->device->serial_number, $request->bearerToken() ?: '', 'device:complete');
        $job = $jobs->complete($device, $job, $validated['status'], $validated['result'] ?? null, $validated['error_message'] ?? null);

        return $this->response(['job' => $this->statusPayload($job)]);
    }

    private function deviceJobPayload(ButtonActionJob $job): array
    {
        $job->loadMissing(['productFunction']);

        return [
            'job_id' => $job->public_id,
            'type' => 'button_function',
            'payload' => [
                'product_function_code' => $job->productFunction->code,
            ],
        ];
    }

    private function statusPayload(ButtonActionJob $job): array
    {
        return [
            'public_id' => $job->public_id,
            'status' => $job->status,
            'progress' => $job->progress,
            'progress_message' => $job->progress_message,
            'error_message' => $job->error_message,
            'result' => $job->result,
            'finished_at' => $job->finished_at?->toISOString(),
        ];
    }
}

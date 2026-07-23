<?php

namespace App\Services\DeviceRuntime;

use App\Exceptions\ApiException;
use App\Models\ButtonActionJob;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

class DeviceJobService
{
    public function poll(Device $device): ?ButtonActionJob
    {
        return DB::transaction(function () use ($device): ?ButtonActionJob {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);

            if ($device->runner_disabled_at !== null) {
                throw new ApiException('Device runtime is disabled.', 'DEVICE_RUNTIME_DISABLED', 403);
            }

            if ($device->runner_current_job_id !== null) {
                $running = ButtonActionJob::query()
                    ->whereKey($device->runner_current_job_id)
                    ->where('status', ButtonActionJob::STATUS_RUNNING)
                    ->first();

                if ($running !== null) {
                    $device->forceFill(['runner_last_seen_at' => now()])->save();

                    return $running;
                }
            }

            $job = ButtonActionJob::query()
                ->where('device_id', $device->id)
                ->where('status', ButtonActionJob::STATUS_QUEUED)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($job === null) {
                $device->forceFill([
                    'runner_status' => 'idle',
                    'runner_current_job_id' => null,
                    'runner_last_seen_at' => now(),
                ])->save();

                return null;
            }

            $job->forceFill([
                'status' => ButtonActionJob::STATUS_RUNNING,
                'locked_by_device_id' => $device->id,
                'started_at' => now(),
                'lease_expires_at' => now()->addMinutes(10),
                'last_progress_at' => now(),
            ])->save();

            $job->events()->create(['type' => 'assigned']);

            $device->forceFill([
                'runner_status' => 'running',
                'runner_current_job_id' => $job->id,
                'runner_last_seen_at' => now(),
            ])->save();

            return $job->refresh();
        });
    }

    public function progress(Device $device, ButtonActionJob $job, int $progress, ?string $message): ButtonActionJob
    {
        $this->ensureJobLockedByDevice($device, $job);

        $job->forceFill([
            'progress' => max(0, min(100, $progress)),
            'progress_message' => $message,
            'last_progress_at' => now(),
            'lease_expires_at' => now()->addMinutes(10),
        ])->save();

        $job->events()->create([
            'type' => 'progress',
            'message' => $message,
            'metadata' => ['progress' => $job->progress],
        ]);

        $device->forceFill([
            'runner_status' => 'running',
            'runner_current_job_id' => $job->id,
            'runner_last_seen_at' => now(),
        ])->save();

        return $job->refresh();
    }

    public function complete(Device $device, ButtonActionJob $job, string $status, ?array $result, ?string $errorMessage): ButtonActionJob
    {
        $this->ensureJobLockedByDevice($device, $job);

        if (! in_array($status, [ButtonActionJob::STATUS_SUCCEEDED, ButtonActionJob::STATUS_FAILED], true)) {
            throw new ApiException('Job state conflict.', 'DEVICE_JOB_STATE_CONFLICT', 409);
        }

        if ($job->isTerminal()) {
            if ($job->status === $status) {
                return $job;
            }

            throw new ApiException('Job state conflict.', 'DEVICE_JOB_STATE_CONFLICT', 409);
        }

        $job->forceFill([
            'status' => $status,
            'progress' => $status === ButtonActionJob::STATUS_SUCCEEDED ? 100 : $job->progress,
            'result' => $result,
            'error_message' => $status === ButtonActionJob::STATUS_FAILED ? $errorMessage : null,
            'finished_at' => now(),
            'lease_expires_at' => null,
        ])->save();

        $job->events()->create([
            'type' => $status === ButtonActionJob::STATUS_SUCCEEDED ? 'completed' : 'failed',
            'message' => $errorMessage,
            'metadata' => $result,
        ]);

        $device->forceFill([
            'runner_status' => 'idle',
            'runner_current_job_id' => null,
            'runner_last_seen_at' => now(),
        ])->save();

        return $job->refresh();
    }

    private function ensureJobLockedByDevice(Device $device, ButtonActionJob $job): void
    {
        if ((int) $job->locked_by_device_id !== (int) $device->id) {
            throw new ApiException('Job is not locked by this device.', 'DEVICE_JOB_NOT_LOCKED', 403);
        }
    }
}

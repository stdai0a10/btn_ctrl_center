<?php

namespace App\Services\Manage;

use App\Models\ButtonActionJob;
use App\Models\Device;
use App\Models\DeviceJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceRuntimeService
{
    public function __construct(private readonly ManageActionLogger $logger) {}

    public function disable(Request $request, Device $device): Device
    {
        return DB::transaction(function () use ($request, $device): Device {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            $before = $this->runtimeSnapshot($device);
            $now = now();

            $this->revokeTokenRecords($device, $now);

            $device->forceFill([
                'runner_disabled_at' => $device->runner_disabled_at ?? $now,
                'runner_status' => 'disabled',
                'runner_current_job_id' => null,
                'runner_last_seen_at' => $now,
                'long_token_revoked_at' => $device->long_token_revoked_at ?? $now,
                'current_access_jti' => null,
                'current_access_expires_at' => null,
                'token_version' => ((int) $device->token_version) + 1,
            ])->save();

            $this->logger->forManageUser(
                request: $request,
                action: 'device_runtime.disable',
                targetType: 'device',
                targetId: $device->id,
                targetPublicId: $device->serial_number,
                metadata: [
                    'before' => $before,
                    'after' => $this->runtimeSnapshot($device->refresh()),
                ],
            );

            return $device;
        });
    }

    public function enable(Request $request, Device $device): Device
    {
        return DB::transaction(function () use ($request, $device): Device {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            $before = $this->runtimeSnapshot($device);

            $device->forceFill([
                'runner_disabled_at' => null,
                'runner_status' => $device->runner_current_job_id === null ? 'registered' : $device->runner_status,
            ])->save();

            $this->logger->forManageUser(
                request: $request,
                action: 'device_runtime.enable',
                targetType: 'device',
                targetId: $device->id,
                targetPublicId: $device->serial_number,
                metadata: [
                    'before' => $before,
                    'after' => $this->runtimeSnapshot($device->refresh()),
                ],
            );

            return $device;
        });
    }

    public function revokeTokens(Request $request, Device $device): Device
    {
        return DB::transaction(function () use ($request, $device): Device {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            $before = $this->runtimeSnapshot($device);
            $now = now();

            $this->revokeTokenRecords($device, $now);

            $device->forceFill([
                'long_token_revoked_at' => $device->long_token_revoked_at ?? $now,
                'current_access_jti' => null,
                'current_access_expires_at' => null,
                'token_version' => ((int) $device->token_version) + 1,
            ])->save();

            $this->logger->forManageUser(
                request: $request,
                action: 'device_runtime.revoke_tokens',
                targetType: 'device',
                targetId: $device->id,
                targetPublicId: $device->serial_number,
                metadata: [
                    'before' => $before,
                    'after' => $this->runtimeSnapshot($device->refresh()),
                ],
            );

            return $device;
        });
    }

    public function cancelJob(Request $request, ButtonActionJob $job): ButtonActionJob
    {
        return DB::transaction(function () use ($request, $job): ButtonActionJob {
            $job = ButtonActionJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($job->isTerminal()) {
                return $job;
            }

            $before = $this->jobSnapshot($job);

            $job->forceFill([
                'status' => ButtonActionJob::STATUS_CANCELED,
                'error_code' => 'MANAGE_CANCELED',
                'error_message' => '任務已由管理員取消。',
                'finished_at' => now(),
                'lease_expires_at' => null,
            ])->save();

            $job->events()->create([
                'type' => 'canceled',
                'message' => '任務已由管理員取消。',
                'metadata' => ['actor_user_public_id' => $request->user()?->public_id],
            ]);

            if ($job->locked_by_device_id !== null) {
                Device::query()
                    ->whereKey($job->locked_by_device_id)
                    ->where('runner_current_job_id', $job->id)
                    ->update([
                        'runner_status' => 'idle',
                        'runner_current_job_id' => null,
                        'updated_at' => now(),
                    ]);
            }

            $this->logger->forManageUser(
                request: $request,
                action: 'button_jobs.cancel',
                targetType: 'button_action_job',
                targetId: $job->id,
                targetPublicId: $job->public_id,
                metadata: [
                    'before' => $before,
                    'after' => $this->jobSnapshot($job->refresh()),
                ],
            );

            return $job;
        });
    }

    private function revokeTokenRecords(Device $device, \DateTimeInterface $now): void
    {
        DeviceJwtToken::query()
            ->where('device_id', $device->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeSnapshot(Device $device): array
    {
        return [
            'serial_number' => $device->serial_number,
            'runner_status' => $device->runner_status,
            'runner_current_job_id' => $device->runner_current_job_id,
            'runner_disabled_at' => $device->runner_disabled_at?->toISOString(),
            'long_token_revoked_at' => $device->long_token_revoked_at?->toISOString(),
            'current_access_expires_at' => $device->current_access_expires_at?->toISOString(),
            'token_version' => $device->token_version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSnapshot(ButtonActionJob $job): array
    {
        return [
            'public_id' => $job->public_id,
            'status' => $job->status,
            'device_id' => $job->device_id,
            'locked_by_device_id' => $job->locked_by_device_id,
            'finished_at' => $job->finished_at?->toISOString(),
        ];
    }
}

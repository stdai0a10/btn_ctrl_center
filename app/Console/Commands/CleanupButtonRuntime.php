<?php

namespace App\Console\Commands;

use App\Models\ButtonActionJob;
use App\Models\Device;
use App\Models\DeviceJwtToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CleanupButtonRuntime extends Command
{
    protected $signature = 'button:cleanup-runtime';

    protected $description = 'Clean up button runtime heartbeats, leases, frontend timeouts, and expired device access tokens.';

    public function handle(): int
    {
        $now = now();
        $utcNow = now('UTC');

        $expiredAccessTokens = DeviceJwtToken::query()
            ->where('type', DeviceJwtToken::TYPE_ACCESS)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', $utcNow)
            ->update(['revoked_at' => $utcNow]);

        $clearedDeviceAccess = Device::query()
            ->whereNotNull('current_access_jti')
            ->where('current_access_expires_at', '<=', $now)
            ->update([
                'current_access_jti' => null,
                'current_access_expires_at' => null,
                'updated_at' => $now,
            ]);

        $offlineDevices = Device::query()
            ->whereNull('runner_disabled_at')
            ->whereIn('runner_status', ['idle', 'running'])
            ->where(function ($query) use ($now): void {
                $query->whereNull('runner_last_seen_at')
                    ->orWhere('runner_last_seen_at', '<=', $now->copy()->subMinutes(5));
            })
            ->update([
                'runner_status' => 'offline',
                'updated_at' => $now,
            ]);

        $expiredLeases = $this->expireLeases($now);
        $offlineJobs = $this->markOfflineJobs($now);
        $frontendTimeouts = $this->markFrontendTimeouts($now);

        $this->components->info(sprintf(
            'Cleaned button runtime: expired_access_tokens=%d cleared_device_access=%d offline_devices=%d expired_leases=%d offline_jobs=%d frontend_timeouts=%d',
            $expiredAccessTokens,
            $clearedDeviceAccess,
            $offlineDevices,
            $expiredLeases,
            $offlineJobs,
            $frontendTimeouts,
        ));

        return self::SUCCESS;
    }

    private function expireLeases(Carbon $now): int
    {
        $count = 0;

        ButtonActionJob::query()
            ->where('status', ButtonActionJob::STATUS_RUNNING)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use ($now, &$count): void {
                foreach ($jobs as $job) {
                    DB::transaction(function () use ($job, $now, &$count): void {
                        $job = ButtonActionJob::query()->lockForUpdate()->find($job->id);
                        if ($job === null || $job->status !== ButtonActionJob::STATUS_RUNNING || $job->lease_expires_at === null || $job->lease_expires_at->greaterThan($now)) {
                            return;
                        }

                        $job->forceFill([
                            'status' => ButtonActionJob::STATUS_TIMED_OUT,
                            'error_code' => 'LEASE_EXPIRED',
                            'error_message' => '設備任務租約已逾時。',
                            'finished_at' => $now,
                            'lease_expires_at' => null,
                        ])->save();
                        $job->events()->create(['type' => 'lease_expired']);

                        Device::query()
                            ->where('runner_current_job_id', $job->id)
                            ->update([
                                'runner_status' => 'idle',
                                'runner_current_job_id' => null,
                                'updated_at' => $now,
                            ]);

                        $count++;
                    });
                }
            });

        return $count;
    }

    private function markOfflineJobs(Carbon $now): int
    {
        $count = 0;

        ButtonActionJob::query()
            ->with('device')
            ->where('status', ButtonActionJob::STATUS_QUEUED)
            ->whereHas('device', function ($query) use ($now): void {
                $query->whereNull('runner_disabled_at')
                    ->where(function ($query) use ($now): void {
                        $query->whereNotIn('runner_status', ['idle', 'running'])
                            ->orWhereNull('runner_last_seen_at')
                            ->orWhere('runner_last_seen_at', '<=', $now->copy()->subMinutes(5));
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use ($now, &$count): void {
                foreach ($jobs as $job) {
                    DB::transaction(function () use ($job, $now, &$count): void {
                        $job = ButtonActionJob::query()->lockForUpdate()->find($job->id);
                        if ($job === null || $job->status !== ButtonActionJob::STATUS_QUEUED) {
                            return;
                        }

                        $job->forceFill([
                            'status' => ButtonActionJob::STATUS_DEVICE_OFFLINE,
                            'error_code' => 'DEVICE_OFFLINE',
                            'error_message' => '設備已離線。',
                            'finished_at' => $now,
                        ])->save();
                        $job->events()->create(['type' => 'device_offline']);

                        $count++;
                    });
                }
            });

        return $count;
    }

    private function markFrontendTimeouts(Carbon $now): int
    {
        $count = 0;
        $cleanupAt = $now->copy()->subMinutes(10);

        ButtonActionJob::query()
            ->whereIn('status', [ButtonActionJob::STATUS_QUEUED, ButtonActionJob::STATUS_RUNNING])
            ->whereNotNull('front_end_timeout_at')
            ->where('front_end_timeout_at', '<=', $cleanupAt)
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use ($now, &$count): void {
                foreach ($jobs as $job) {
                    DB::transaction(function () use ($job, $now, &$count): void {
                        $job = ButtonActionJob::query()->lockForUpdate()->find($job->id);
                        if ($job === null || ! in_array($job->status, [ButtonActionJob::STATUS_QUEUED, ButtonActionJob::STATUS_RUNNING], true)) {
                            return;
                        }

                        $job->forceFill([
                            'status' => ButtonActionJob::STATUS_TIMED_OUT,
                            'error_code' => 'FRONTEND_TIMEOUT_EXPIRED',
                            'error_message' => '前端等待逾時後任務仍未完成。',
                            'finished_at' => $now,
                            'lease_expires_at' => null,
                        ])->save();
                        $job->events()->create(['type' => 'frontend_timeout_expired']);

                        Device::query()
                            ->where('runner_current_job_id', $job->id)
                            ->update([
                                'runner_status' => 'idle',
                                'runner_current_job_id' => null,
                                'updated_at' => $now,
                            ]);

                        $count++;
                    });
                }
            });

        return $count;
    }
}

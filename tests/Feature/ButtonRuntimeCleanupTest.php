<?php

namespace Tests\Feature;

use App\Models\ButtonActionJob;
use App\Models\Device;
use App\Models\DeviceJwtToken;
use App\Models\Product;
use App\Models\ProductFunction;
use App\Models\User;
use App\Services\DeviceRuntime\DeviceJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ButtonRuntimeCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_marks_offline_devices_jobs_and_expired_access_tokens(): void
    {
        [$device, $function] = $this->createDevice([
            'runner_status' => 'idle',
            'runner_last_seen_at' => now()->subMinutes(6),
            'current_access_jti' => 'expired-access',
            'current_access_expires_at' => now()->subMinute(),
        ]);
        DeviceJwtToken::query()->create([
            'jti' => 'expired-access',
            'device_id' => $device->id,
            'type' => DeviceJwtToken::TYPE_ACCESS,
            'token_version' => 1,
            'issued_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);
        $job = $this->createJob($device, $function, ButtonActionJob::STATUS_QUEUED, [
            'request_id' => 'req-offline',
        ]);

        $this->artisan('button:cleanup-runtime')->assertExitCode(0);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'runner_status' => 'offline',
            'current_access_jti' => null,
            'current_access_expires_at' => null,
        ]);
        $this->assertNotNull(DeviceJwtToken::query()->where('jti', 'expired-access')->value('revoked_at'));
        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $job->id,
            'status' => ButtonActionJob::STATUS_DEVICE_OFFLINE,
            'error_code' => 'DEVICE_OFFLINE',
        ]);
    }

    public function test_cleanup_expires_running_jobs_when_lease_is_expired(): void
    {
        [$device, $function] = $this->createDevice(['runner_status' => 'running']);
        $job = $this->createJob($device, $function, ButtonActionJob::STATUS_RUNNING, [
            'request_id' => 'req-lease',
            'locked_by_device_id' => $device->id,
            'lease_expires_at' => now()->subSecond(),
            'started_at' => now()->subMinutes(11),
        ]);
        $device->forceFill(['runner_current_job_id' => $job->id])->save();

        $this->artisan('button:cleanup-runtime')->assertExitCode(0);

        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $job->id,
            'status' => ButtonActionJob::STATUS_TIMED_OUT,
            'error_code' => 'LEASE_EXPIRED',
        ]);
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'runner_status' => 'idle',
            'runner_current_job_id' => null,
        ]);
    }

    public function test_cleanup_keeps_frontend_timeout_jobs_until_grace_period_expires(): void
    {
        [$device, $function] = $this->createDevice();
        $recent = $this->createJob($device, $function, ButtonActionJob::STATUS_QUEUED, [
            'request_id' => 'req-recent-timeout',
            'front_end_timeout_at' => now()->subMinutes(2),
        ]);
        $old = $this->createJob($device, $function, ButtonActionJob::STATUS_QUEUED, [
            'request_id' => 'req-old-timeout',
            'front_end_timeout_at' => now()->subMinutes(11),
        ]);

        $this->artisan('button:cleanup-runtime')->assertExitCode(0);

        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $recent->id,
            'status' => ButtonActionJob::STATUS_QUEUED,
        ]);
        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $old->id,
            'status' => ButtonActionJob::STATUS_TIMED_OUT,
            'error_code' => 'FRONTEND_TIMEOUT_EXPIRED',
        ]);
    }

    public function test_poll_keeps_current_running_job_and_uses_device_scoped_fifo(): void
    {
        [$device, $function] = $this->createDevice();
        [$otherDevice] = $this->createDevice(['serial_number' => 'DEVICE-OTHER']);
        $otherJob = $this->createJob($otherDevice, $function, ButtonActionJob::STATUS_QUEUED, [
            'request_id' => 'req-other',
        ]);
        $first = $this->createJob($device, $function, ButtonActionJob::STATUS_QUEUED, [
            'request_id' => 'req-first',
        ]);
        $second = $this->createJob($device, $function, ButtonActionJob::STATUS_QUEUED, [
            'request_id' => 'req-second',
        ]);

        $service = app(DeviceJobService::class);
        $polled = $service->poll($device);
        $polledAgain = $service->poll($device->refresh());

        $this->assertSame($first->id, $polled?->id);
        $this->assertSame($first->id, $polledAgain?->id);
        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $first->id,
            'status' => ButtonActionJob::STATUS_RUNNING,
            'locked_by_device_id' => $device->id,
        ]);
        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $second->id,
            'status' => ButtonActionJob::STATUS_QUEUED,
        ]);
        $this->assertDatabaseHas('button_action_jobs', [
            'id' => $otherJob->id,
            'status' => ButtonActionJob::STATUS_QUEUED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{Device, ProductFunction}
     */
    private function createDevice(array $attributes = []): array
    {
        $product = Product::factory()->create();
        $function = ProductFunction::factory()->create([
            'product_id' => $product->id,
            'is_enabled' => true,
        ]);
        $device = Device::factory()->create([
            'product_id' => $product->id,
            'serial_number' => 'DEVICE-CLEANUP',
            'runner_status' => 'idle',
            'runner_last_seen_at' => now(),
            ...$attributes,
        ]);

        return [$device, $function];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createJob(Device $device, ProductFunction $function, string $status, array $attributes = []): ButtonActionJob
    {
        return ButtonActionJob::query()->create([
            'user_id' => User::factory()->create()->id,
            'request_id' => 'req-'.strtolower($status).'-'.uniqid(),
            'device_id' => $device->id,
            'product_function_id' => $function->id,
            'status' => $status,
            'source' => ButtonActionJob::SOURCE_BUTTON,
            ...$attributes,
        ]);
    }
}

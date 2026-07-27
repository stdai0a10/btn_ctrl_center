<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceJwtToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceRuntimeTimestampTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_device_timestamps_convert_utc_values_to_the_database_session_timezone(): void
    {
        $taipeiNow = Carbon::parse('2026-07-27 09:00:00', 'Asia/Taipei');
        Carbon::setTestNow($taipeiNow);
        $utcNow = $taipeiNow->copy()->utc();

        $device = Device::factory()->create([
            'long_token_issued_at' => $utcNow,
            'long_token_expires_at' => $utcNow->copy()->addYear(),
            'long_token_revoked_at' => $utcNow->copy()->addMinute(),
            'current_access_expires_at' => $utcNow->copy()->addMinutes(15),
            'runner_last_seen_at' => $utcNow,
            'runner_registered_at' => $utcNow,
            'runner_disabled_at' => $utcNow->copy()->addMinutes(2),
            'system_disabled_at' => $utcNow->copy()->addMinutes(3),
        ]);

        $raw = DB::table('devices')->where('id', $device->id)->first();

        $this->assertSame('2026-07-27 09:00:00', substr((string) $raw->long_token_issued_at, 0, 19));
        $this->assertSame('2027-07-27 09:00:00', substr((string) $raw->long_token_expires_at, 0, 19));
        $this->assertSame('2026-07-27 09:01:00', substr((string) $raw->long_token_revoked_at, 0, 19));
        $this->assertSame('2026-07-27 09:15:00', substr((string) $raw->current_access_expires_at, 0, 19));
        $this->assertSame('2026-07-27 09:00:00', substr((string) $raw->runner_last_seen_at, 0, 19));
        $this->assertSame('2026-07-27 09:00:00', substr((string) $raw->runner_registered_at, 0, 19));
        $this->assertSame('2026-07-27 09:02:00', substr((string) $raw->runner_disabled_at, 0, 19));
        $this->assertSame('2026-07-27 09:03:00', substr((string) $raw->system_disabled_at, 0, 19));
        $this->assertSame('2026-07-27 09:00:00', substr((string) $raw->created_at, 0, 19));
        $this->assertSame('2026-07-27 09:00:00', substr((string) $raw->updated_at, 0, 19));

        $fresh = Device::query()->findOrFail($device->id);

        $this->assertSame(config('app.timezone'), $fresh->current_access_expires_at->timezoneName);
        $this->assertSame('2026-07-27 09:15:00', $fresh->current_access_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_cleanup_keeps_an_unexpired_access_token_created_from_utc_times(): void
    {
        $taipeiNow = Carbon::parse('2026-07-27 09:00:00', 'Asia/Taipei');
        Carbon::setTestNow($taipeiNow);
        $utcNow = $taipeiNow->copy()->utc();

        $device = Device::factory()->create([
            'token_version' => 1,
            'current_access_jti' => 'active-access',
            'current_access_expires_at' => $utcNow->copy()->addMinutes(15),
            'runner_status' => 'idle',
            'runner_last_seen_at' => $utcNow,
        ]);
        $token = DeviceJwtToken::query()->create([
            'jti' => 'active-access',
            'device_id' => $device->id,
            'type' => DeviceJwtToken::TYPE_ACCESS,
            'token_version' => 1,
            'issued_at' => $utcNow,
            'expires_at' => $utcNow->copy()->addMinutes(15),
        ]);

        $this->artisan('button:cleanup-runtime')->assertExitCode(0);

        $this->assertSame('active-access', $device->refresh()->current_access_jti);
        $this->assertNull($token->refresh()->revoked_at);
        $this->assertSame('idle', $device->runner_status);

        $this->travel(16)->minutes();
        $this->artisan('button:cleanup-runtime')->assertExitCode(0);

        $this->assertNull($device->refresh()->current_access_jti);
        $this->assertNotNull($token->refresh()->revoked_at);
    }
}

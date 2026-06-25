<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceJwtToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceJwtTokenTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_jwt_token_times_are_stored_as_utc_and_read_in_app_timezone(): void
    {
        $device = Device::factory()->create();
        $taipeiTime = Carbon::parse('2026-06-25 20:00:00', 'Asia/Taipei');

        $token = DeviceJwtToken::query()->create([
            'jti' => 'timezone-token',
            'device_id' => $device->id,
            'type' => DeviceJwtToken::TYPE_ACCESS,
            'token_version' => 1,
            'issued_at' => $taipeiTime,
            'expires_at' => $taipeiTime->copy()->addMinutes(15),
            'revoked_at' => $taipeiTime->copy()->addMinutes(5),
            'last_used_at' => $taipeiTime->copy()->addMinutes(2),
        ]);

        $raw = DB::table('device_jwt_tokens')->where('id', $token->id)->first();

        $this->assertSame('2026-06-25 12:00:00', substr((string) $raw->issued_at, 0, 19));
        $this->assertSame('2026-06-25 12:15:00', substr((string) $raw->expires_at, 0, 19));
        $this->assertSame('2026-06-25 12:05:00', substr((string) $raw->revoked_at, 0, 19));
        $this->assertSame('2026-06-25 12:02:00', substr((string) $raw->last_used_at, 0, 19));

        $fresh = DeviceJwtToken::query()->findOrFail($token->id);

        $this->assertSame(config('app.timezone'), $fresh->issued_at->timezoneName);
        $this->assertSame('2026-06-25 20:00:00', $fresh->issued_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-25 20:15:00', $fresh->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-25 20:05:00', $fresh->revoked_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-25 20:02:00', $fresh->last_used_at->format('Y-m-d H:i:s'));
    }
}

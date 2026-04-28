<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\PasswordResetRequest;
use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_email_can_request_and_use_password_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => 'old-password-password',
            'status' => 'active',
        ]);
        $email = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => 'reset@example.com',
        ]);
        $user->forceFill(['primary_email_id' => $email->id])->save();

        DB::table('sessions')->insert([
            'id' => 'existing-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'RESET@example.com',
        ])->assertOk()
            ->assertJsonPath('message', '若資料正確，系統已寄出密碼變更信。');

        $request = PasswordResetRequest::query()->firstOrFail();
        $token = str_repeat('b', 64);
        $request->forceFill(['token_hash' => hash('sha256', $token)])->save();

        $this->getJson('/api/auth/reset-password?token='.$token)
            ->assertOk()
            ->assertJsonPath('message', '密碼重設連結有效。');

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'password' => 'new-password-password',
            'password_confirmation' => 'new-password-password',
        ])->assertOk()
            ->assertJsonPath('message', '密碼變更完成。');

        $this->assertTrue(Hash::check('new-password-password', $user->refresh()->password));
        $this->assertNotNull($request->refresh()->used_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'password' => 'another-password-password',
            'password_confirmation' => 'another-password-password',
        ])->assertUnprocessable();
    }

    public function test_missing_or_unverified_email_gets_generic_response_without_request(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk();

        $this->assertSame(0, PasswordResetRequest::query()->count());
    }
}

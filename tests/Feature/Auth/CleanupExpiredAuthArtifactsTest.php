<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\EmailVerificationRequest;
use App\Models\Auth\PasswordResetRequest;
use App\Models\Auth\SecurityReauthLog;
use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupExpiredAuthArtifactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_expires_tokens_and_removes_stale_auth_data(): void
    {
        $pendingUser = User::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        UserEmail::factory()->unverified()->create([
            'user_id' => $pendingUser->id,
            'email' => 'expired-registration@example.com',
            'reserved_until' => now()->subMinute(),
        ]);

        $activeUser = User::factory()->create(['status' => 'active']);

        $emailRequest = EmailVerificationRequest::query()->create([
            'user_id' => $activeUser->id,
            'email' => 'token@example.com',
            'purpose' => 'change_email',
            'token_hash' => hash('sha256', 'email-token'),
            'expires_at' => now()->subMinute(),
        ]);

        $passwordRequest = PasswordResetRequest::query()->create([
            'user_id' => $activeUser->id,
            'email' => 'token@example.com',
            'token_hash' => hash('sha256', 'password-token'),
            'expires_at' => now()->subMinute(),
        ]);

        $reauthLog = SecurityReauthLog::query()->create([
            'user_id' => $activeUser->id,
            'method' => 'password',
            'passed_at' => now()->subDays(3),
            'expires_at' => now()->subDays(2),
        ]);

        $this->artisan('auth:cleanup-expired')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $pendingUser->id]);
        $this->assertNotNull($emailRequest->refresh()->invalidated_at);
        $this->assertNotNull($passwordRequest->refresh()->invalidated_at);
        $this->assertDatabaseMissing('security_reauth_logs', ['id' => $reauthLog->id]);
    }
}

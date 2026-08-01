<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\EmailVerificationRequest;
use App\Models\Auth\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_verify_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register/email', [
            'email' => 'USER@example.com',
            'password' => 'password-password',
            'password_confirmation' => 'password-password',
        ])->assertOk();

        $userEmail = UserEmail::query()->where('email', 'user@example.com')->firstOrFail();

        $this->assertFalse($userEmail->is_verified);
        $this->assertSame('pending', $userEmail->user->status);

        $request = EmailVerificationRequest::query()->firstOrFail();

        $token = str_repeat('a', 64);
        $request->forceFill(['token_hash' => hash('sha256', $token)])->save();

        $this->getJson('/api/auth/email/verify?token='.$token)
            ->assertOk()
            ->assertJsonPath('message', 'EMAIL 驗證完成。');

        $userEmail->refresh();

        $this->assertTrue($userEmail->is_verified);
        $this->assertTrue($userEmail->is_primary);
        $this->assertSame('active', $userEmail->user->refresh()->status);
        $this->assertSame($userEmail->id, $userEmail->user->primary_email_id);
    }

    public function test_duplicate_email_registration_does_not_create_another_account(): void
    {
        Mail::fake();

        $payload = [
            'email' => 'same@example.com',
            'password' => 'password-password',
            'password_confirmation' => 'password-password',
        ];

        $this->postJson('/api/auth/register/email', $payload)->assertOk();
        $this->postJson('/api/auth/register/email', $payload)->assertOk();

        $this->assertSame(1, UserEmail::query()->where('email', 'same@example.com')->count());
    }
}

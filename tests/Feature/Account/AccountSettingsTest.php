<?php

namespace Tests\Feature\Account;

use App\Models\Auth\EmailVerificationRequest;
use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_and_update_profile_name(): void
    {
        $user = $this->verifiedUser('profile@example.com');

        $this->actingAs($user)
            ->getJson('/api/account/profile')
            ->assertOk()
            ->assertJsonPath('data.public_id', $user->public_id)
            ->assertJsonPath('data.email.current', 'profile@example.com');

        $this->actingAs($user)
            ->putJson('/api/account/profile', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $user->refresh()->name);
    }

    public function test_user_can_request_and_verify_email_change(): void
    {
        Mail::fake();
        $user = $this->verifiedUser('old@example.com');

        $this->actingAs($user)
            ->postJson('/api/account/email/change-request', ['email' => 'NEW@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('data.reauth.0', '此操作需要重新驗證身分。');

        $this->actingAs($user)
            ->postJson('/api/account/reauth', ['password' => 'password-password'])
            ->assertOk()
            ->assertJsonPath('message', '重新驗證完成。');

        $this->actingAs($user)
            ->postJson('/api/account/email/change-request', ['email' => 'NEW@example.com'])
            ->assertOk()
            ->assertJsonPath('message', '系統已寄出 EMAIL 變更驗證信。');

        $this->assertDatabaseHas('user_emails', [
            'user_id' => $user->id,
            'email' => 'new@example.com',
            'is_verified' => false,
        ]);

        $request = EmailVerificationRequest::query()->where('purpose', 'change_email')->firstOrFail();
        $token = str_repeat('c', 64);
        $request->forceFill(['token_hash' => hash('sha256', $token)])->save();

        $this->getJson('/api/auth/email/verify?token='.$token)->assertOk();

        $newEmail = UserEmail::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($newEmail->is_verified);
        $this->assertTrue($newEmail->is_primary);
        $this->assertSame($newEmail->id, $user->refresh()->primary_email_id);
    }

    public function test_user_must_reauth_before_changing_password(): void
    {
        $user = $this->verifiedUser('security@example.com');

        $this->actingAs($user)
            ->putJson('/api/account/password', [
                'current_password' => 'password-password',
                'password' => 'blocked-password-password',
                'password_confirmation' => 'blocked-password-password',
            ])->assertUnprocessable()
            ->assertJsonPath('data.reauth.0', '此操作需要重新驗證身分。');

        $this->actingAs($user)
            ->postJson('/api/account/reauth', ['password' => 'password-password'])
            ->assertOk();

        $this->actingAs($user)
            ->putJson('/api/account/password', [
                'current_password' => 'password-password',
                'password' => 'second-password-password',
                'password_confirmation' => 'second-password-password',
            ])->assertOk();

        $this->assertTrue(Hash::check('second-password-password', $user->refresh()->password));
    }

    private function verifiedUser(string $email, ?string $password = 'password-password'): User
    {
        $user = User::factory()->create([
            'password' => $password,
            'status' => 'active',
        ]);

        $userEmail = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => $email,
        ]);

        $user->forceFill(['primary_email_id' => $userEmail->id])->save();

        return $user;
    }
}

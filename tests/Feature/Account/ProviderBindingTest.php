<?php

namespace Tests\Feature\Account;

use App\Models\Auth\UserAuthProvider;
use App\Models\Auth\UserEmail;
use App\Models\User;
use App\Services\Auth\AccountBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_unbind_line_after_reauth_when_password_login_remains(): void
    {
        $user = $this->verifiedUser('provider@example.com');
        UserAuthProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'line',
            'provider_user_id' => 'line-to-delete',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/account/providers/line')
            ->assertUnprocessable()
            ->assertJsonPath('data.reauth.0', '此操作需要重新驗證身分。');

        $this->actingAs($user)
            ->postJson('/api/account/reauth', ['password' => 'password-password'])
            ->assertOk();

        $this->actingAs($user)
            ->deleteJson('/api/account/providers/line')
            ->assertOk()
            ->assertJsonPath('message', 'LINE 綁定已解除。');

        $this->assertDatabaseMissing('user_auth_providers', [
            'provider' => 'line',
            'provider_user_id' => 'line-to-delete',
        ]);
    }

    public function test_line_binding_rejects_provider_already_bound_to_other_user(): void
    {
        $firstUser = $this->verifiedUser('first@example.com');
        $secondUser = $this->verifiedUser('second@example.com');

        UserAuthProvider::factory()->create([
            'user_id' => $firstUser->id,
            'provider' => 'line',
            'provider_user_id' => 'shared-line',
        ]);

        $this->expectException(ValidationException::class);

        app(AccountBindingService::class)->bindLine($secondUser, 'shared-line', 'Line Name');
    }

    public function test_line_only_account_cannot_unbind_last_login_method(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'status' => 'active',
        ]);
        UserAuthProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'line',
            'provider_user_id' => 'line-only',
        ]);

        $this->expectException(ValidationException::class);

        app(AccountBindingService::class)->unbindLine($user);
    }

    private function verifiedUser(string $email): User
    {
        $user = User::factory()->create([
            'password' => 'password-password',
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

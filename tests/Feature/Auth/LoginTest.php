<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_login_logout_and_fetch_me(): void
    {
        $user = User::factory()->create([
            'password' => 'password-password',
            'status' => 'active',
        ]);
        $email = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => 'login@example.com',
        ]);
        $user->forceFill(['primary_email_id' => $email->id])->save();

        $this->withHeader('referer', 'http://localhost:8000/login')
            ->postJson('/api/auth/login/email', [
            'email' => 'LOGIN@example.com',
            'password' => 'password-password',
        ])->assertOk()
            ->assertJsonPath('data.public_id', $user->public_id)
            ->assertJsonPath('data.email', 'login@example.com');

        $this->assertAuthenticatedAs($user);

        $this->withHeader('referer', 'http://localhost:8000/account/profile')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.public_id', $user->public_id);

        $this->withHeader('referer', 'http://localhost:8000/account/profile')
            ->postJson('/api/auth/logout')
            ->assertOk();
        $this->assertGuest();
    }

    public function test_login_returns_requested_redirect_or_home_by_default(): void
    {
        $user = User::factory()->create([
            'password' => 'password-password',
            'status' => 'active',
        ]);
        $email = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => 'redirect@example.com',
        ]);
        $user->forceFill(['primary_email_id' => $email->id])->save();

        $this->withHeader('referer', 'http://localhost:8000/login')
            ->postJson('/api/auth/login/email', [
            'email' => 'redirect@example.com',
            'password' => 'password-password',
            'redirect' => '/rooms?tab=devices',
        ])->assertOk()
            ->assertJsonPath('data.redirect_to', '/rooms?tab=devices');

        $this->withHeader('referer', 'http://localhost:8000/rooms')
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->withHeader('referer', 'http://localhost:8000/login')
            ->postJson('/api/auth/login/email', [
            'email' => 'redirect@example.com',
            'password' => 'password-password',
        ])->assertOk()
            ->assertJsonPath('data.redirect_to', '/');
    }

    public function test_login_returns_session_intended_url_for_protected_pages(): void
    {
        $user = User::factory()->create([
            'password' => 'password-password',
            'status' => 'active',
        ]);
        $email = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => 'intended@example.com',
        ]);
        $user->forceFill(['primary_email_id' => $email->id])->save();

        $this->get('/rooms')->assertRedirect('/login');

        $this->withHeader('referer', 'http://localhost:8000/login')
            ->postJson('/api/auth/login/email', [
            'email' => 'intended@example.com',
            'password' => 'password-password',
        ])->assertOk()
            ->assertJsonPath('data.redirect_to', '/rooms');
    }

    public function test_unverified_email_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'password-password',
            'status' => 'pending',
        ]);
        UserEmail::factory()->unverified()->create([
            'user_id' => $user->id,
            'email' => 'pending@example.com',
        ]);

        $this->postJson('/api/auth/login/email', [
            'email' => 'pending@example.com',
            'password' => 'password-password',
        ])->assertUnprocessable();

        $this->assertGuest();
    }
}

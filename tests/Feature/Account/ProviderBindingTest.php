<?php

namespace Tests\Feature\Account;

use App\Models\Auth\UserAuthProvider;
use App\Models\Auth\UserEmail;
use App\Models\User;
use App\Services\Auth\AccountBindingService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Tests\TestCase;

class ProviderBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_binding_requires_recent_reauthentication(): void
    {
        $user = $this->verifiedUser('bind-reauth@example.com');

        $this->actingAs($user)
            ->getJson('/api/account/providers/line/bind')
            ->assertUnprocessable()
            ->assertJsonPath('data.reauth.0', '此操作需要重新驗證身分。');
    }

    public function test_user_can_start_line_binding_with_configured_https_callback_after_reauthentication(): void
    {
        $user = $this->verifiedUser('bind-line@example.com');
        $lineCallbackUrl = 'https://button.example.test/api/account/providers/line/callback';

        config()->set('services.line.client_id', 'test-channel');
        config()->set('services.line.client_secret', 'test-secret');
        config()->set('services.line.binding_redirect', $lineCallbackUrl);

        $this->actingAs($user)
            ->postJson('/api/account/reauth', ['password' => 'password-password'])
            ->assertOk();

        $response = $this->actingAs($user)
            ->get('http://origin.example.test/api/account/providers/line/bind');

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $response->assertRedirect()
            ->assertSessionHas('line_oauth_intent', 'bind');
        $this->assertStringStartsWith('https://access.line.me/oauth2/v2.1/authorize?', $location);
        $this->assertSame($lineCallbackUrl, $query['redirect_uri'] ?? null);
    }

    public function test_line_oauth_routes_use_one_web_session_stack_without_stateful_api_middleware(): void
    {
        $routes = [
            ['GET', '/api/auth/line/redirect', false],
            ['GET', '/api/auth/line/callback', false],
            ['GET', '/api/account/providers/line/bind', true],
            ['POST', '/api/account/providers/line/bind', true],
            ['GET', '/api/account/providers/line/callback', true],
        ];

        foreach ($routes as [$method, $uri, $requiresAuthentication]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $middleware = app('router')->gatherRouteMiddleware($route);

            $this->assertSame(1, count(array_filter(
                $middleware,
                fn ($item): bool => $item === StartSession::class,
            )), "{$method} {$uri} should use exactly one session middleware.");
            $this->assertNotContains(
                EnsureFrontendRequestsAreStateful::class,
                $middleware,
                "{$method} {$uri} should not inherit the API middleware stack.",
            );

            if ($requiresAuthentication) {
                $this->assertContains(Authenticate::class, $middleware);
            }
        }
    }

    public function test_line_binding_callback_keeps_the_authenticated_session_across_the_oauth_round_trip(): void
    {
        config()->set('session.driver', 'database');
        app('session')->forgetDrivers();

        $user = $this->verifiedUser('bind-cookie-round-trip@example.com');
        $lineCallbackUrl = 'https://button.example.test/api/account/providers/line/callback';
        config()->set('services.line.binding_redirect', $lineCallbackUrl);

        $loginResponse = $this->withHeader('referer', 'http://localhost/login')
            ->postJson('/api/auth/login/email', [
                'email' => 'bind-cookie-round-trip@example.com',
                'password' => 'password-password',
            ])
            ->assertOk();
        $this->carrySessionCookie($loginResponse);
        $this->forgetRequestAuthenticationState();

        $reauthResponse = $this->withCredentials()
            ->withHeader('referer', 'http://localhost/account/providers')
            ->postJson('/api/account/reauth', ['password' => 'password-password'])
            ->assertOk();
        $this->carrySessionCookie($reauthResponse);
        $this->forgetRequestAuthenticationState();

        $lineUser = new SocialiteUser;
        $lineUser->id = 'line-cookie-round-trip';
        $lineUser->name = 'Cookie Round Trip';
        $lineUser->token = 'line-access-token';
        $lineUser->refreshToken = 'line-refresh-token';

        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->twice()->with($lineCallbackUrl)->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(
            new SymfonyRedirectResponse('https://access.line.me/oauth2/v2.1/authorize?state=test-state'),
        );
        $driver->shouldReceive('user')->once()->andReturn($lineUser);
        Socialite::shouldReceive('driver')->twice()->with('line')->andReturn($driver);

        $bindResponse = $this->get('/api/account/providers/line/bind')
            ->assertRedirect('https://access.line.me/oauth2/v2.1/authorize?state=test-state')
            ->assertSessionHas('line_oauth_intent', 'bind');
        $this->carrySessionCookie($bindResponse);
        $this->forgetRequestAuthenticationState();

        $this->withHeader('referer', 'https://access.line.me/')
            ->get('/api/account/providers/line/callback?code=test-code&state=test-state')
            ->assertRedirect(route('account.providers'));

        $this->assertDatabaseHas('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'line',
            'provider_user_id' => 'line-cookie-round-trip',
        ]);
    }

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

    private function carrySessionCookie($response): void
    {
        $cookie = $response->getCookie((string) config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
    }

    private function forgetRequestAuthenticationState(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
    }
}

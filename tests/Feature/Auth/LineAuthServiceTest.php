<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\UserAuthProvider;
use App\Services\Auth\LineAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_login_registers_new_user_with_provider_snapshot(): void
    {
        $user = app(LineAuthService::class)->loginOrRegisterFromProvider(
            'line-user-1',
            'Line Display Name',
            'access-token',
            'refresh-token',
        );

        $this->assertSame('active', $user->status);
        $this->assertSame('Line Display Name', $user->name);
        $this->assertNull($user->password);
        $this->assertDatabaseHas('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'line',
            'provider_user_id' => 'line-user-1',
            'provider_name_snapshot' => 'Line Display Name',
        ]);
    }

    public function test_line_login_reuses_existing_provider_user(): void
    {
        $service = app(LineAuthService::class);

        $firstUser = $service->loginOrRegisterFromProvider('line-user-2', 'First Name', 'first-token');
        $secondUser = $service->loginOrRegisterFromProvider('line-user-2', 'Changed Name', 'second-token');

        $this->assertTrue($firstUser->is($secondUser));
        $this->assertSame(1, UserAuthProvider::query()->where('provider_user_id', 'line-user-2')->count());
        $this->assertSame('First Name', $secondUser->refresh()->name);
    }
}

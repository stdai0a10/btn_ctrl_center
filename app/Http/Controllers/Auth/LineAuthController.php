<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\LineAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class LineAuthController extends ApiController
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('line')->redirect();
    }

    public function callback(Request $request, LineAuthService $lineAuthService): RedirectResponse
    {
        $lineUser = Socialite::driver('line')->user();

        $user = $lineAuthService->loginOrRegisterFromProvider(
            (string) $lineUser->getId(),
            $lineUser->getName(),
            $lineUser->token ?? null,
            $lineUser->refreshToken ?? null,
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.profile')->with('success', 'LINE 登入成功。');
    }

    public function liff(Request $request, LineAuthService $lineAuthService)
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $user = $lineAuthService->loginOrRegisterFromLiffAccessToken($validated['access_token']);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->response([
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
        ], 'LINE LIFF 登入成功。');
    }
}

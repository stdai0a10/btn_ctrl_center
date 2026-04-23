<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LineAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class LineAuthController extends Controller
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
}

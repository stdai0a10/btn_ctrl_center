<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use App\Services\Auth\AccountBindingService;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ProviderBindingController extends ApiController
{
    public function lineRedirect(Request $request, ReauthenticationService $reauthenticationService): SymfonyRedirectResponse
    {
        $reauthenticationService->assertFresh($request->user());
        $request->session()->put('line_oauth_intent', 'bind');

        return Socialite::driver('line')
            ->redirectUrl(route('account.providers.line.callback'))
            ->redirect();
    }

    public function lineCallback(Request $request, AccountBindingService $accountBindingService): RedirectResponse
    {
        if ($request->session()->pull('line_oauth_intent') !== 'bind') {
            return redirect()->route('account.providers')->with('error', 'LINE 綁定流程已失效，請重新操作。');
        }

        $lineUser = Socialite::driver('line')
            ->redirectUrl(route('account.providers.line.callback'))
            ->user();

        $accountBindingService->bindLine(
            $request->user(),
            (string) $lineUser->getId(),
            $lineUser->getName(),
            $lineUser->token ?? null,
            $lineUser->refreshToken ?? null,
        );

        return redirect()->route('account.providers')->with('success', 'LINE 綁定完成。');
    }

    public function destroyLine(
        Request $request,
        AccountBindingService $accountBindingService,
        ReauthenticationService $reauthenticationService,
    ) {
        $reauthenticationService->assertFresh($request->user());
        $accountBindingService->unbindLine($request->user());

        return $this->response(null, 'LINE 綁定已解除。');
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use App\Services\Auth\AccountBindingService;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ProviderBindingController extends ApiController
{
    #[OA\Get(
        path: '/api/account/providers/line/bind',
        operationId: 'accountLineBindRedirect',
        summary: 'Start LINE account binding',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to LINE authorization.'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    #[OA\Post(
        path: '/api/account/providers/line/bind',
        operationId: 'accountLineBindRedirectPost',
        summary: 'Start LINE account binding with POST',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to LINE authorization.'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function lineRedirect(Request $request, ReauthenticationService $reauthenticationService): SymfonyRedirectResponse
    {
        $reauthenticationService->assertFresh($request->user());
        $request->session()->put('line_oauth_intent', 'bind');

        return Socialite::driver('line')
            ->redirectUrl($this->lineRedirectUri())
            ->redirect();
    }

    #[OA\Get(
        path: '/api/account/providers/line/callback',
        operationId: 'accountLineBindCallback',
        summary: 'Complete LINE account binding',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to the account provider settings.'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function lineCallback(Request $request, AccountBindingService $accountBindingService): RedirectResponse
    {
        if ($request->session()->pull('line_oauth_intent') !== 'bind') {
            return redirect()->route('account.providers')->with('error', 'LINE 綁定流程已失效，請重新操作。');
        }

        $lineUser = Socialite::driver('line')
            ->redirectUrl($this->lineRedirectUri())
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

    #[OA\Delete(
        path: '/api/account/providers/line',
        operationId: 'accountLineUnbind',
        summary: 'Remove LINE account binding',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function destroyLine(
        Request $request,
        AccountBindingService $accountBindingService,
        ReauthenticationService $reauthenticationService,
    ) {
        $reauthenticationService->assertFresh($request->user());
        $accountBindingService->unbindLine($request->user());

        return $this->response(null, 'LINE 綁定已解除。');
    }

    private function lineRedirectUri(): string
    {
        return (string) config('services.line.binding_redirect');
    }
}

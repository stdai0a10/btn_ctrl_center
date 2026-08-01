<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\LineAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class LineAuthController extends ApiController
{
    #[OA\Get(
        path: '/api/auth/line/redirect',
        operationId: 'authLineRedirect',
        summary: 'Start LINE OAuth login',
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to LINE authorization.'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('line')->redirect();
    }

    #[OA\Get(
        path: '/api/auth/line/callback',
        operationId: 'authLineCallback',
        summary: 'Complete LINE OAuth login',
        tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to the account profile.'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function callback(Request $request, LineAuthService $lineAuthService): RedirectResponse
    {
        $lineUser = Socialite::driver('line')->user();

        $user = $lineAuthService->loginOrRegisterFromProvider(
            (string) $lineUser->getId(),
            $lineUser->getName(),
            $lineUser->token ?? null,
            $lineUser->refreshToken ?? null,
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('account.profile')->with('success', 'LINE 登入成功。');
    }

    #[OA\Post(
        path: '/api/auth/line/liff',
        operationId: 'authLineLiff',
        summary: 'Log in with a LINE LIFF access token',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LiffLoginRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function liff(Request $request, LineAuthService $lineAuthService)
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $user = $lineAuthService->loginOrRegisterFromLiffAccessToken($validated['access_token']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->response([
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
        ], 'LINE LIFF 登入成功。');
    }
}

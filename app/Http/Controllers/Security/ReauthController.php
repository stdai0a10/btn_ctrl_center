<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\ApiController;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReauthController extends ApiController
{
    #[OA\Get(
        path: '/api/account/reauth',
        operationId: 'accountReauthStatus',
        summary: 'Get the reauthentication status',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request, ReauthenticationService $reauthenticationService)
    {
        return $this->response([
            'is_valid' => $reauthenticationService->isFresh($request->user()),
        ]);
    }

    #[OA\Post(
        path: '/api/account/reauth',
        operationId: 'accountReauth',
        summary: 'Reauthenticate with the account password',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PasswordRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, ReauthenticationService $reauthenticationService)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $log = $reauthenticationService->passWithPassword($request->user(), $validated['password'], $request);

        return $this->response([
            'expires_at' => $log->expires_at,
        ], '重新驗證完成。');
    }
}

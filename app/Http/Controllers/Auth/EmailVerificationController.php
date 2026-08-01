<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EmailVerificationController extends ApiController
{
    #[OA\Get(
        path: '/api/auth/email/verify',
        operationId: 'authVerifyEmail',
        summary: 'Verify an email address',
        tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 64, maxLength: 64)),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function verify(Request $request, EmailVerificationService $emailVerificationService)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $purpose = $emailVerificationService->verify($validated['token']);

        return $this->response([
            'purpose' => $purpose,
        ], 'EMAIL 驗證完成。');
    }

    #[OA\Post(
        path: '/api/auth/email/resend',
        operationId: 'authResendVerificationEmail',
        summary: 'Resend an email verification message',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmailRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function resend(Request $request, EmailVerificationService $emailVerificationService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $emailVerificationService->resend($validated['email'], $request->ip());

        return $this->response(null, '若 EMAIL 可用，系統已寄出驗證信。');
    }
}

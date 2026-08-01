<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ForgotPasswordController extends ApiController
{
    #[OA\Post(
        path: '/api/auth/forgot-password',
        operationId: 'authForgotPassword',
        summary: 'Request a password reset email',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmailRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $passwordResetService->sendResetLink($validated['email'], $request->ip());

        return $this->response(null, '若資料正確，系統已寄出密碼變更信。');
    }
}

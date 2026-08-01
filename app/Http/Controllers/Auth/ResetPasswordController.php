<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class ResetPasswordController extends ApiController
{
    #[OA\Get(
        path: '/api/auth/reset-password',
        operationId: 'authValidateResetPasswordToken',
        summary: 'Validate a password reset token',
        tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 64, maxLength: 64)),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $passwordResetService->validateToken($validated['token']);

        return $this->response(null, '密碼重設連結有效。');
    }

    #[OA\Post(
        path: '/api/auth/reset-password',
        operationId: 'authResetPassword',
        summary: 'Reset a password with a valid token',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PasswordResetRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $passwordResetService->reset($validated['token'], $validated['password']);

        return $this->response(null, '密碼變更完成。');
    }
}

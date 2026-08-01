<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class RegisterController extends ApiController
{
    #[OA\Post(
        path: '/api/auth/register/email',
        operationId: 'authRegisterEmail',
        summary: 'Register an account with email and password',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmailRegistrationRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, UserRegistrationService $registrationService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $registrationService->registerEmail(
            $validated['email'],
            $validated['password'],
            $request->ip(),
        );

        return $this->response(null, '若 EMAIL 可用，系統已寄出驗證信。');
    }
}

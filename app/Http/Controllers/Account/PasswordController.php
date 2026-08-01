<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PasswordController extends ApiController
{
    #[OA\Put(
        path: '/api/account/password',
        operationId: 'accountPasswordUpdate',
        summary: 'Set or update the account password',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PasswordUpdateRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function update(Request $request, ReauthenticationService $reauthenticationService)
    {
        $user = $request->user();
        $reauthenticationService->assertFresh($user);

        $user->loadMissing('primaryEmail');

        if (! $user->primaryEmail?->is_verified) {
            throw ValidationException::withMessages([
                'email' => '需完成 EMAIL 驗證後才能設定密碼。',
            ]);
        }

        $rules = [
            'password' => ['required', 'confirmed', Password::min(12)],
        ];

        if ($user->password !== null) {
            $rules['current_password'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        if ($user->password !== null && ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => '目前密碼不正確。',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return $this->response(null, '密碼已更新。');
    }
}

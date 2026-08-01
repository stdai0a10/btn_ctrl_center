<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use App\Models\Auth\UserEmail;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class EmailController extends ApiController
{
    #[OA\Post(
        path: '/api/account/email/change-request',
        operationId: 'accountEmailChangeRequest',
        summary: 'Request an account email change',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmailRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(
        Request $request,
        EmailVerificationService $emailVerificationService,
        ReauthenticationService $reauthenticationService,
    ) {
        $reauthenticationService->assertFresh($request->user());

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = $request->user();
        $user->loadMissing('primaryEmail');

        if ($user->primaryEmail?->email === $email) {
            throw ValidationException::withMessages([
                'email' => '此 EMAIL 已是目前帳號 EMAIL。',
            ]);
        }

        if (UserEmail::query()->where('email', $email)->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => '此 EMAIL 已被其他帳號使用或仍處於保留有效期間內。',
            ]);
        }

        DB::transaction(function () use ($user, $email, $request, $emailVerificationService): void {
            UserEmail::query()
                ->where('user_id', $user->id)
                ->where('is_verified', false)
                ->where('email', '!=', $email)
                ->delete();

            UserEmail::query()->updateOrCreate(
                ['user_id' => $user->id, 'email' => $email],
                [
                    'is_verified' => false,
                    'is_primary' => false,
                    'verified_at' => null,
                    'reserved_until' => now()->addDay(),
                ],
            );

            $emailVerificationService->createRequest($user, $email, 'change_email', $request->ip());
        });

        return $this->response(null, '系統已寄出 EMAIL 變更驗證信。');
    }
}

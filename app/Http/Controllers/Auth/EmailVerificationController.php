<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\Request;

class EmailVerificationController extends ApiController
{
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

    public function resend(Request $request, EmailVerificationService $emailVerificationService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $emailVerificationService->resend($validated['email'], $request->ip());

        return $this->response(null, '若 EMAIL 可用，系統已寄出驗證信。');
    }
}

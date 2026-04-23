<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\Request;

class ForgotPasswordController extends ApiController
{
    public function store(Request $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $passwordResetService->sendResetLink($validated['email'], $request->ip());

        return $this->response(null, '若資料正確，系統已寄出密碼變更信。');
    }
}

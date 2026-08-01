<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ResetPasswordController extends ApiController
{
    public function show(Request $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $passwordResetService->validateToken($validated['token']);

        return $this->response(null, '密碼重設連結有效。');
    }

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

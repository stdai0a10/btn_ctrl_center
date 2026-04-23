<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class RegisterController extends ApiController
{
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

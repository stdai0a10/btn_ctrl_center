<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends ApiController
{
    public function update(Request $request)
    {
        $user = $request->user();
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

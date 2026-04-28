<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    public function show(Request $request)
    {
        return $this->response($this->payload($request->user()));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->forceFill([
            'name' => $validated['name'] ?? null,
        ])->save();

        return $this->response($this->payload($request->user()->refresh()), '帳號資料已更新。');
    }

    private function payload($user): array
    {
        $user->loadMissing(['primaryEmail', 'emails', 'authProviders']);
        $pendingEmail = $user->emails
            ->where('is_verified', false)
            ->sortByDesc('created_at')
            ->first();

        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
            'email' => [
                'current' => $user->primaryEmail?->email,
                'is_verified' => (bool) $user->primaryEmail?->is_verified,
                'pending' => $pendingEmail?->email,
            ],
            'password' => [
                'is_set' => $user->password !== null,
            ],
            'providers' => [
                'line' => $user->authProviders->contains('provider', 'line'),
            ],
        ];
    }
}

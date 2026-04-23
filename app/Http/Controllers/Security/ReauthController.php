<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\ApiController;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\Request;

class ReauthController extends ApiController
{
    public function show(Request $request, ReauthenticationService $reauthenticationService)
    {
        return $this->response([
            'is_valid' => $reauthenticationService->isFresh($request->user()),
        ]);
    }

    public function store(Request $request, ReauthenticationService $reauthenticationService)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $log = $reauthenticationService->passWithPassword($request->user(), $validated['password'], $request);

        return $this->response([
            'expires_at' => $log->expires_at,
        ], '重新驗證完成。');
    }
}

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'message' => 'success',
    'data' => [
        'status' => 'ok',
        'app' => config('app.name'),
    ],
]));

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

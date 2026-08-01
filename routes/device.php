<?php

use App\Http\Controllers\DeviceAuthController;
use App\Http\Controllers\DeviceJobController;
use Illuminate\Support\Facades\Route;

Route::post('/device-auth/long-token', [DeviceAuthController::class, 'longToken']);
Route::post('/devices/{serial_number}/access-tokens', [DeviceAuthController::class, 'accessToken']);
Route::post('/devices/{serial_number}/poll', [DeviceJobController::class, 'poll']);
Route::post('/device-jobs/{button_action_job_public_id}/progress', [DeviceJobController::class, 'progress']);
Route::post('/device-jobs/{button_action_job_public_id}/complete', [DeviceJobController::class, 'complete']);

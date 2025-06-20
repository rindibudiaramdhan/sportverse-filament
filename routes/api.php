<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return ['status' => 'pong', 'version' => config('app.version')];
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
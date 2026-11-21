<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function() {
    
});

Route::middleware(['auth:api', 'role:admin, petugas'])->prefix('operasional')->group(function() {

});

Route::middleware(['auth:api', 'role:admin, owner'])->prefix('laporan')->group(function() {

});
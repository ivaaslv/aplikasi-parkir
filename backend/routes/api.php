<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Master\AreaParkirController;
use App\Http\Controllers\Api\Master\KendaraanController;
use App\Http\Controllers\Api\Master\TarifController;
use App\Http\Controllers\Api\Master\UserController;
use App\Http\Controllers\Api\TransaksiController;
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
    Route::apiResource('users', UserController::class);
    Route::apiResource('tarif', TarifController::class);
    Route::apiResource('area-parkir', AreaParkirController::class);
    Route::apiResource('kendaraan', KendaraanController::class);
});

Route::middleware(['auth:api', 'role:admin, petugas'])->prefix('operasional')->group(function() {
    Route::get('transaksi', [TransaksiController::class, 'index']);
    Route::post('transaksi/masuk', [TransaksiController::class, 'masuk']);
    Route::post('transaksi/keluar', [TransaksiController::class, 'keluar']);
    Route::get('transaksi/{id}/struk', [TransaksiController::class, 'struk']);
});

Route::middleware(['auth:api', 'role:admin, owner'])->prefix('laporan')->group(function() {

});
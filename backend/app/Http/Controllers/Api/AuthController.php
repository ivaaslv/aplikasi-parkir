<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LogAktivitas;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah',
            ], 401);
        }

        $user = auth('api')->user();

        if ((int) $user->status_aktif !== 1) {
            auth('api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif, hubungi admin',
            ], 403);
        }

        LogAktivitas::create([
            'id_user' => $user->id_user,
            'aktivitas' => 'Login ke Sistem',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user,
        ], 200);
    }

    public function me()
    {
        return response()->json([
            'success' => true,
            'user' => auth('api')->user(),
        ], 200);
    }

    public function logout()
    {
        $user = auth('api')->user();

        if ($user) {
            LogAktivitas::create([
                'id_user' => $user->id_user,
                'aktivitas' => 'Logout dari sistem',
                'waktu_aktivitas' => now(),
            ]);
        }

        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ], 200);
    }

    public function refresh()
    {
        $newToken = auth('api')->refresh();

        return response()->json([
            'success' => true,
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], 200);
    }
}
<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UserRequest;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => User::orderByDesc('id_user')->get(),
        ], 200);
    }

    public function store(UserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'nama_lengkap' => $validated['nama_lengkap'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status_aktif' => array_key_exists('status_aktif', $validated)
                ? (int) filter_var($validated['status_aktif'], FILTER_VALIDATE_BOOLEAN)
                : 1,
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menambah user {$user->username} ({$user->role})",
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan',
            'data' => $user,
        ], 201);
    }

    public function show(string $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ], 200);
    }

    public function update(UserRequest $request, string $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $validated = $request->validated();
        $payload = [];

        foreach (['nama_lengkap', 'username', 'role'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('status_aktif', $validated)) {
            $payload['status_aktif'] = (int) filter_var($validated['status_aktif'], FILTER_VALIDATE_BOOLEAN);
        }

        $user->update($payload);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah data user {$user->username}",
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui',
            'data' => $user->fresh(),
        ], 200);
    }

    public function destroy(string $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ((int) $user->id_user === (int) auth('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus akun sendiri yang sedang login',
            ], 400);
        }

        $username = $user->username;
        $user->delete();

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menghapus user {$username}",
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus',
        ], 200);
    }
}
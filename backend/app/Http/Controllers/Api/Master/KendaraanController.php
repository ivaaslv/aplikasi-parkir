<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Kendaraan;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KendaraanController extends Controller
{
    public function index()
    {
        $kendaraan = Kendaraan::with('user')->orderByDesc('id_kendaraan')->get();

        return response()->json([
            'success' => true,
            'data' => $kendaraan,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plat_nomor' => 'required|string|max:20',
            'jenis_kendaraan' => 'required|string|max:50',
            'warna' => 'nullable|string|max:30',
            'pemilik' => 'nullable|string|max:100',
            'id_user' => 'nullable|exists:tb_user,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kendaraan = Kendaraan::create([
            'plat_nomor' => strtoupper(trim($request->plat_nomor)),
            'jenis_kendaraan' => strtolower($request->jenis_kendaraan),
            'warna' => $request->warna,
            'pemilik' => $request->pemilik,
            'id_user' => $request->id_user ?? auth('api')->id(),
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menambah data kendaraan {$kendaraan->plat_nomor}",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data kendaraan berhasil ditambahkan',
            'data' => $kendaraan->load('user'),
        ], 201);
    }

    public function show(string $id)
    {
        $kendaraan = Kendaraan::with('user')->find($id);

        if (! $kendaraan) {
            return response()->json([
                'success' => false,
                'message' => 'Data kendaraan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $kendaraan,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $kendaraan = Kendaraan::find($id);

        if (! $kendaraan) {
            return response()->json([
                'success' => false,
                'message' => 'Data kendaraan tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'plat_nomor' => 'sometimes|required|string|max:20',
            'jenis_kendaraan' => 'sometimes|required|string|max:50',
            'warna' => 'nullable|string|max:30',
            'pemilik' => 'nullable|string|max:100',
            'id_user' => 'nullable|exists:tb_user,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kendaraan->update([
            'plat_nomor' => $request->has('plat_nomor') ? strtoupper(trim($request->plat_nomor)) : $kendaraan->plat_nomor,
            'jenis_kendaraan' => $request->has('jenis_kendaraan') ? strtolower($request->jenis_kendaraan) : $kendaraan->jenis_kendaraan,
            'warna' => $request->has('warna') ? $request->warna : $kendaraan->warna,
            'pemilik' => $request->has('pemilik') ? $request->pemilik : $kendaraan->pemilik,
            'id_user' => $request->has('id_user') ? $request->id_user : $kendaraan->id_user,
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah data kendaraan {$kendaraan->plat_nomor}",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data kendaraan berhasil diperbarui',
            'data' => $kendaraan->fresh('user'),
        ], 200);
    }

    public function destroy(string $id)
    {
        $kendaraan = Kendaraan::find($id);

        if (! $kendaraan) {
            return response()->json([
                'success' => false,
                'message' => 'Data kendaraan tidak ditemukan',
            ], 404);
        }

        $plat = $kendaraan->plat_nomor;
        $kendaraan->delete();

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menghapus data kendaraan {$plat}",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data kendaraan berhasil dihapus',
        ], 200);
    }
}
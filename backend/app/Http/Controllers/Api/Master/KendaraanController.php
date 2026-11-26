<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\KendaraanRequest;
use App\Models\Kendaraan;
use App\Models\LogAktivitas;

class KendaraanController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Kendaraan::with('user')->orderByDesc('id_kendaraan')->get(),
        ], 200);
    }

    public function store(KendaraanRequest $request)
    {
        $validated = $request->validated();

        $kendaraan = Kendaraan::create([
            'plat_nomor' => strtoupper(trim($validated['plat_nomor'])),
            'jenis_kendaraan' => strtolower(trim($validated['jenis_kendaraan'])),
            'warna' => $validated['warna'] ?? null,
            'pemilik' => $validated['pemilik'] ?? null,
            'id_user' => $validated['id_user'] ?? auth('api')->id(),
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menambah data kendaraan {$kendaraan->plat_nomor}",
            'status' => 'berhasil',
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

    public function update(KendaraanRequest $request, string $id)
    {
        $kendaraan = Kendaraan::find($id);

        if (! $kendaraan) {
            return response()->json([
                'success' => false,
                'message' => 'Data kendaraan tidak ditemukan',
            ], 404);
        }

        $validated = $request->validated();

        $kendaraan->update([
            'plat_nomor' => array_key_exists('plat_nomor', $validated)
                ? strtoupper(trim($validated['plat_nomor']))
                : $kendaraan->plat_nomor,
            'jenis_kendaraan' => array_key_exists('jenis_kendaraan', $validated)
                ? strtolower(trim($validated['jenis_kendaraan']))
                : $kendaraan->jenis_kendaraan,
            'warna' => array_key_exists('warna', $validated) ? $validated['warna'] : $kendaraan->warna,
            'pemilik' => array_key_exists('pemilik', $validated) ? $validated['pemilik'] : $kendaraan->pemilik,
            'id_user' => array_key_exists('id_user', $validated) ? $validated['id_user'] : $kendaraan->id_user,
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah data kendaraan {$kendaraan->plat_nomor}",
            'status' => 'berhasil',
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
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data kendaraan berhasil dihapus',
        ], 200);
    }
}
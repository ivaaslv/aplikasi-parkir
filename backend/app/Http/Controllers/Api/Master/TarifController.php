<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TarifController extends Controller
{
    public function index()
    {
        $tarif = Tarif::orderBy('id_tarif')->get();

        return response()->json([
            'success' => true,
            'data' => $tarif,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_kendaraan' => 'required|string|max:50|unique:tb_tarif,jenis_kendaraan',
            'tarif_per_jam' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tarif = Tarif::create([
            'jenis_kendaraan' => strtolower($request->jenis_kendaraan),
            'tarif_per_jam' => $request->tarif_per_jam,
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menambah tarif {$tarif->jenis_kendaraan} ({$tarif->tarif_per_jam}/jam)",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil ditambahkan',
            'data' => $tarif,
        ], 201);
    }

    public function show(string $id)
    {
        $tarif = Tarif::find($id);

        if (! $tarif) {
            return response()->json([
                'success' => false,
                'message' => 'Tarif tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tarif,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $tarif = Tarif::find($id);

        if (! $tarif) {
            return response()->json([
                'success' => false,
                'message' => 'Tarif tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'jenis_kendaraan' => 'sometimes|required|string|max:50|unique:tb_tarif,jenis_kendaraan,' . $tarif->id_tarif . ',id_tarif',
            'tarif_per_jam' => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [];
        if ($request->has('jenis_kendaraan')) {
            $payload['jenis_kendaraan'] = strtolower($request->jenis_kendaraan);
        }
        if ($request->has('tarif_per_jam')) {
            $payload['tarif_per_jam'] = $request->tarif_per_jam;
        }

        $tarif->update($payload);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah tarif {$tarif->jenis_kendaraan} menjadi {$tarif->tarif_per_jam}/jam",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil diperbarui',
            'data' => $tarif->fresh(),
        ], 200);
    }

    public function destroy(string $id)
    {
        $tarif = Tarif::find($id);

        if (! $tarif) {
            return response()->json([
                'success' => false,
                'message' => 'Tarif tidak ditemukan',
            ], 404);
        }

        $jenis = $tarif->jenis_kendaraan;
        $tarif->delete();

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menghapus tarif {$jenis}",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil dihapus',
        ], 200);
    }
}
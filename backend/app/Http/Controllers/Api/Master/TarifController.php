<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\TarifRequest;
use App\Models\LogAktivitas;
use App\Models\Tarif;
use Illuminate\Database\QueryException;

class TarifController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Tarif::orderBy('id_tarif')->get(),
        ], 200);
    }

    public function store(TarifRequest $request)
    {
        $validated = $request->validated();

        $tarif = Tarif::create([
            'jenis_kendaraan' => strtolower(trim($validated['jenis_kendaraan'])),
            'tarif_per_jam' => $validated['tarif_per_jam'],
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menambah tarif {$tarif->jenis_kendaraan} ({$tarif->tarif_per_jam}/jam)",
            'status' => 'berhasil',
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

    public function update(TarifRequest $request, string $id)
    {
        $tarif = Tarif::find($id);

        if (! $tarif) {
            LogAktivitas::create([
                'id_user' => auth('api')->id(),
                'aktivitas' => "Gagal mengubah tarif (ID #{$id} tidak ditemukan)",
                'status' => 'gagal',
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tarif tidak ditemukan',
            ], 404);
        }

        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('jenis_kendaraan', $validated)) {
            $payload['jenis_kendaraan'] = strtolower(trim($validated['jenis_kendaraan']));
        }

        if (array_key_exists('tarif_per_jam', $validated)) {
            $payload['tarif_per_jam'] = $validated['tarif_per_jam'];
        }

        $tarif->update($payload);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah tarif {$tarif->jenis_kendaraan} menjadi {$tarif->tarif_per_jam}/jam",
            'status' => 'berhasil',
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
            LogAktivitas::create([
                'id_user' => auth('api')->id(),
                'aktivitas' => "Gagal menghapus tarif (ID #{$id} tidak ditemukan)",
                'status' => 'gagal',
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tarif tidak ditemukan',
            ], 404);
        }

        $jenis = $tarif->jenis_kendaraan;

        try {
            $tarif->delete();
        } catch (QueryException $e) {
            LogAktivitas::create([
                'id_user' => auth('api')->id(),
                'aktivitas' => "Gagal menghapus tarif {$jenis}: masih digunakan pada data transaksi",
                'status' => 'gagal',
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tarif tidak dapat dihapus karena masih digunakan pada riwayat transaksi',
            ], 409);
        }

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menghapus tarif {$jenis}",
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil dihapus',
        ], 200);
    }
}
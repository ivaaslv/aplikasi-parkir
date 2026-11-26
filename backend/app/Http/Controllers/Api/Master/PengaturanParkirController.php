<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use App\Models\PengaturanParkir;
use Illuminate\Http\Request;

class PengaturanParkirController extends Controller
{
    public function show()
    {
        $config = PengaturanParkir::firstOrCreate([], [
            'denda_karcis_hilang' => 25000,
            'skema_denda' => 'tambahan',
        ]);

        return response()->json([
            'success' => true,
            'data' => $config,
        ], 200);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'denda_karcis_hilang' => ['required', 'numeric', 'min:0'],
            'skema_denda' => ['required', 'in:tambahan,pengganti'],
        ]);

        $config = PengaturanParkir::firstOrCreate([], [
            'denda_karcis_hilang' => 25000,
            'skema_denda' => 'tambahan',
        ]);

        $config->update([
            'denda_karcis_hilang' => (int) $validated['denda_karcis_hilang'],
            'skema_denda' => $validated['skema_denda'],
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah denda karcis hilang menjadi Rp {$config->denda_karcis_hilang} ({$config->skema_denda})",
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan denda karcis hilang berhasil diperbarui',
            'data' => $config->fresh(),
        ], 200);
    }
}
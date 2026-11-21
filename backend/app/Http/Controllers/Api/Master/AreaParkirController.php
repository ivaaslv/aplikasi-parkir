<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AreaParkirRequest;
use App\Models\AreaParkir;
use App\Models\LogAktivitas;

class AreaParkirController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => AreaParkir::orderBy('id_area')->get(),
        ], 200);
    }

    public function store(AreaParkirRequest $request)
    {
        $validated = $request->validated();

        $area = AreaParkir::create([
            'nama_area' => trim($validated['nama_area']),
            'kapasitas' => (int) $validated['kapasitas'],
            'terisi' => isset($validated['terisi']) ? (int) $validated['terisi'] : 0,
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menambah area parkir {$area->nama_area} (kapasitas {$area->kapasitas})",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Area parkir berhasil ditambahkan',
            'data' => $area,
        ], 201);
    }

    public function show(string $id)
    {
        $area = AreaParkir::find($id);

        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Area parkir tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $area,
        ], 200);
    }

    public function update(AreaParkirRequest $request, string $id)
    {
        $area = AreaParkir::find($id);

        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Area parkir tidak ditemukan',
            ], 404);
        }

        $validated = $request->validated();
        $kapasitasBaru = isset($validated['kapasitas']) ? (int) $validated['kapasitas'] : (int) $area->kapasitas;
        $terisiBaru = isset($validated['terisi']) ? (int) $validated['terisi'] : (int) $area->terisi;

        if ($terisiBaru > $kapasitasBaru) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah slot terisi tidak boleh melebihi kapasitas area',
            ], 422);
        }

        $area->update([
            'nama_area' => isset($validated['nama_area']) ? trim($validated['nama_area']) : $area->nama_area,
            'kapasitas' => $kapasitasBaru,
            'terisi' => $terisiBaru,
        ]);

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Mengubah area parkir {$area->nama_area}",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Area parkir berhasil diperbarui',
            'data' => $area->fresh(),
        ], 200);
    }

    public function destroy(string $id)
    {
        $area = AreaParkir::find($id);

        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Area parkir tidak ditemukan',
            ], 404);
        }

        if ((int) $area->terisi > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Area parkir tidak bisa dihapus karena masih ada slot terisi',
            ], 400);
        }

        $namaArea = $area->nama_area;
        $area->delete();

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menghapus area parkir {$namaArea}",
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Area parkir berhasil dihapus',
        ], 200);
    }
}
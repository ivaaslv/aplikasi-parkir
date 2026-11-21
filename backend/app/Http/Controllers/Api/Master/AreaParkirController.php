<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\AreaParkir;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AreaParkirController extends Controller
{
    public function index()
    {
        $areas = AreaParkir::orderBy('id_area')->get();

        return response()->json([
            'success' => true,
            'data' => $areas,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_area' => 'required|string|max:100|unique:tb_area_parkir,nama_area',
            'kapasitas' => 'required|integer|min:1',
            'terisi' => 'nullable|integer|min:0|lte:kapasitas',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $area = AreaParkir::create([
            'nama_area' => $request->nama_area,
            'kapasitas' => $request->kapasitas,
            'terisi' => $request->terisi ?? 0,
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

    public function update(Request $request, string $id)
    {
        $area = AreaParkir::find($id);

        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Area parkir tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_area' => 'sometimes|required|string|max:100|unique:tb_area_parkir,nama_area,' . $area->id_area . ',id_area',
            'kapasitas' => 'sometimes|required|integer|min:1',
            'terisi' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kapasitasBaru = $request->has('kapasitas') ? (int) $request->kapasitas : (int) $area->kapasitas;
        $terisiBaru = $request->has('terisi') ? (int) $request->terisi : (int) $area->terisi;

        if ($terisiBaru > $kapasitasBaru) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah slot terisi tidak boleh melebihi kapasitas area',
            ], 422);
        }

        $area->update([
            'nama_area' => $request->nama_area ?? $area->nama_area,
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
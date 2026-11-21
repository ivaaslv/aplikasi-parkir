<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AreaParkir;
use App\Models\Kendaraan;
use App\Models\LogAktivitas;
use App\Models\Tarif;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaksi::with(['kendaraan', 'tarif', 'areaParkir', 'user'])
            ->orderByDesc('id_parkir');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plat_nomor')) {
            $plat = strtoupper(trim($request->plat_nomor));
            $query->whereHas('kendaraan', function ($q) use ($plat) {
                $q->where('plat_nomor', 'like', "%{$plat}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ], 200);
    }

    public function masuk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plat_nomor' => 'required|string|max:20',
            'jenis_kendaraan' => 'required|string|max:50',
            'warna' => 'nullable|string|max:30',
            'pemilik' => 'nullable|string|max:100',
            'id_area' => 'required|exists:tb_area_parkir,id_area',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $plat = strtoupper(trim($request->plat_nomor));
        $jenis = strtolower(trim($request->jenis_kendaraan));

        $tarif = Tarif::whereRaw('LOWER(jenis_kendaraan) = ?', [$jenis])->first();
        if (! $tarif) {
            return response()->json([
                'success' => false,
                'message' => "Tarif untuk jenis kendaraan '{$jenis}' belum diatur",
            ], 422);
        }

        try {
            $transaksi = DB::transaction(function () use ($request, $plat, $jenis, $tarif) {
                $area = AreaParkir::where('id_area', $request->id_area)->lockForUpdate()->first();

                if ((int) $area->terisi >= (int) $area->kapasitas) {
                    throw new \RuntimeException('Kapasitas area parkir sudah penuh');
                }

                $kendaraan = Kendaraan::firstOrCreate(
                    ['plat_nomor' => $plat],
                    [
                        'jenis_kendaraan' => $jenis,
                        'warna' => $request->warna,
                        'pemilik' => $request->pemilik,
                        'id_user' => auth('api')->id(),
                    ]
                );

                // Update info kendaraan jika dikirim dan berubah
                $kendaraan->update([
                    'jenis_kendaraan' => $jenis,
                    'warna' => $request->filled('warna') ? $request->warna : $kendaraan->warna,
                    'pemilik' => $request->filled('pemilik') ? $request->pemilik : $kendaraan->pemilik,
                ]);

                $sudahMasuk = Transaksi::where('id_kendaraan', $kendaraan->id_kendaraan)
                    ->where('status', 'masuk')
                    ->exists();

                if ($sudahMasuk) {
                    throw new \RuntimeException("Kendaraan {$plat} masih berstatus masuk/parkir");
                }

                $transaksi = Transaksi::create([
                    'id_kendaraan' => $kendaraan->id_kendaraan,
                    'waktu_masuk' => now(),
                    'waktu_keluar' => null,
                    'id_tarif' => $tarif->id_tarif,
                    'durasi_jam' => 0,
                    'biaya_total' => 0,
                    'status' => 'masuk',
                    'id_user' => auth('api')->id(),
                    'id_area' => $area->id_area,
                ]);

                $area->increment('terisi');

                LogAktivitas::create([
                    'id_user' => auth('api')->id(),
                    'aktivitas' => "Parkir masuk {$plat} di {$area->nama_area}",
                    'waktu_aktivitas' => now(),
                ]);

                return $transaksi->load(['kendaraan', 'tarif', 'areaParkir', 'user']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi parkir masuk berhasil dicatat',
                'data' => $transaksi,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function keluar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_parkir' => 'nullable|exists:tb_transaksi,id_parkir',
            'plat_nomor' => 'nullable|string|max:20',
        ]);

        if ($validator->fails() || (! $request->filled('id_parkir') && ! $request->filled('plat_nomor'))) {
            return response()->json([
                'success' => false,
                'message' => 'Sertakan id_parkir atau plat_nomor kendaraan yang keluar',
            ], 422);
        }

        try {
            $transaksi = DB::transaction(function () use ($request) {
                $query = Transaksi::with(['kendaraan', 'tarif', 'areaParkir'])
                    ->where('status', 'masuk');

                if ($request->filled('id_parkir')) {
                    $query->where('id_parkir', $request->id_parkir);
                } else {
                    $plat = strtoupper(trim($request->plat_nomor));
                    $query->whereHas('kendaraan', function ($q) use ($plat) {
                        $q->where('plat_nomor', $plat);
                    });
                }

                $trx = $query->lockForUpdate()->first();

                if (! $trx) {
                    throw new \RuntimeException('Transaksi parkir aktif tidak ditemukan');
                }

                $waktuKeluar = now();
                $menit = max(1, $trx->waktu_masuk->diffInMinutes($waktuKeluar));
                $durasiJam = (int) ceil($menit / 60);
                $tarifPerJam = (float) ($trx->tarif->tarif_per_jam ?? 0);
                $biayaTotal = $durasiJam * $tarifPerJam;

                $trx->update([
                    'waktu_keluar' => $waktuKeluar,
                    'durasi_jam' => $durasiJam,
                    'biaya_total' => $biayaTotal,
                    'status' => 'keluar',
                ]);

                if ($trx->areaParkir) {
                    $area = AreaParkir::where('id_area', $trx->id_area)->lockForUpdate()->first();
                    if ($area && (int) $area->terisi > 0) {
                        $area->decrement('terisi');
                    }
                }

                $platNomor = $trx->kendaraan->plat_nomor ?? '-';
                LogAktivitas::create([
                    'id_user' => auth('api')->id(),
                    'aktivitas' => "Parkir keluar {$platNomor} (Durasi {$durasiJam} jam, Biaya Rp {$biayaTotal})",
                    'waktu_aktivitas' => now(),
                ]);

                return $trx->fresh(['kendaraan', 'tarif', 'areaParkir', 'user']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi parkir keluar berhasil diproses',
                'data' => $transaksi,
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function struk(string $id)
    {
        $transaksi = Transaksi::with(['kendaraan', 'tarif', 'areaParkir', 'user'])->find($id);

        if (! $transaksi) {
            return response()->json([
                'success' => false,
                'message' => 'Data transaksi / struk tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'struk' => [
                'no_transaksi' => 'PRK-' . str_pad((string) $transaksi->id_parkir, 6, '0', STR_PAD_LEFT),
                'status' => $transaksi->status,
                'plat_nomor' => $transaksi->kendaraan->plat_nomor ?? null,
                'jenis_kendaraan' => $transaksi->kendaraan->jenis_kendaraan ?? null,
                'area_parkir' => $transaksi->areaParkir->nama_area ?? null,
                'waktu_masuk' => $transaksi->waktu_masuk,
                'waktu_keluar' => $transaksi->waktu_keluar,
                'durasi_jam' => $transaksi->durasi_jam,
                'tarif_per_jam' => $transaksi->tarif->tarif_per_jam ?? 0,
                'biaya_total' => $transaksi->biaya_total,
                'petugas' => $transaksi->user->nama_lengkap ?? null,
            ],
            'raw' => $transaksi,
        ], 200);
    }
}
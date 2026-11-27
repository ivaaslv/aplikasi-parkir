<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\TransaksiKeluarRequest;
use App\Http\Requests\Transaksi\TransaksiMasukRequest;
use App\Models\AreaParkir;
use App\Models\Kendaraan;
use App\Models\LogAktivitas;
use App\Models\PengaturanParkir;
use App\Models\Tarif;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function masuk(TransaksiMasukRequest $request)
    {
        $validated = $request->validated();

        $plat = strtoupper(trim($validated['plat_nomor']));
        $jenis = strtolower(trim($validated['jenis_kendaraan']));
        $warnaInput = isset($validated['warna']) && trim((string) $validated['warna']) !== ''
            ? trim((string) $validated['warna'])
            : '-';
        $pemilikInput = isset($validated['pemilik']) && trim((string) $validated['pemilik']) !== ''
            ? trim((string) $validated['pemilik'])
            : 'Tamu Umum';

        $tarif = Tarif::whereRaw('LOWER(jenis_kendaraan) = ?', [$jenis])->first();
        if (! $tarif) {
            LogAktivitas::create([
                'id_user' => auth('api')->id(),
                'aktivitas' => Str::limit("Gagal masuk {$plat}: tarif '{$jenis}' belum diatur", 240, '...'),
                'status' => 'gagal',
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Tarif untuk jenis kendaraan '{$jenis}' belum diatur",
            ], 422);
        }

        try {
            $transaksi = DB::transaction(function () use ($validated, $plat, $jenis, $warnaInput, $pemilikInput, $tarif) {
                $area = AreaParkir::where('id_area', $validated['id_area'])->lockForUpdate()->first();

                if (! $area) {
                    throw new \RuntimeException('Area parkir tidak ditemukan');
                }

                $jenisArea = strtolower(trim((string) ($area->jenis_kendaraan ?? 'semua')));
                if ($jenisArea !== '' && $jenisArea !== 'semua' && $jenisArea !== $jenis) {
                    throw new \RuntimeException("Area {$area->nama_area} khusus untuk {$jenisArea}");
                }

                if ((int) $area->terisi >= (int) $area->kapasitas) {
                    throw new \RuntimeException('Kapasitas area parkir sudah penuh');
                }

                $kendaraan = Kendaraan::firstOrCreate(
                    ['plat_nomor' => $plat],
                    [
                        'jenis_kendaraan' => $jenis,
                        'warna' => $warnaInput,
                        'pemilik' => $pemilikInput,
                        'id_user' => auth('api')->id(),
                    ]
                );

                $kendaraan->update([
                    'jenis_kendaraan' => $jenis,
                    'warna' => $warnaInput,
                    'pemilik' => $pemilikInput,
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
                    'aktivitas' => Str::limit("Parkir masuk {$plat} di {$area->nama_area}", 240, '...'),
                    'status' => 'berhasil',
                    'waktu_aktivitas' => now(),
                ]);

                return $transaksi->load(['kendaraan', 'tarif', 'areaParkir', 'user']);
            });

            $noTransaksi = 'PRK-' . str_pad((string) $transaksi->id_parkir, 6, '0', STR_PAD_LEFT);
            $dendaConfig = PengaturanParkir::first();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi parkir masuk berhasil dicatat',
                'no_transaksi' => $noTransaksi,
                'denda_karcis_hilang' => (float) ($dendaConfig->denda_karcis_hilang ?? 25000),
                'skema_denda' => $dendaConfig->skema_denda ?? 'tambahan',
                'data' => $transaksi,
            ], 201);
        } catch (\Throwable $e) {
            $alasanSingkat = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Gagal memproses data kendaraan/transaksi';

            LogAktivitas::create([
                'id_user' => auth('api')->id(),
                'aktivitas' => Str::limit("Gagal masuk {$plat}: {$alasanSingkat}", 240, '...'),
                'status' => 'gagal',
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $alasanSingkat,
            ], 422);
        }
    }

    public function keluar(TransaksiKeluarRequest $request)
    {
        $validated = $request->validated();
        $karcisHilang = (bool) $request->boolean('karcis_hilang');

        $rawInput = trim((string) ($validated['kode_tiket'] ?? $validated['plat_nomor'] ?? ''));
        $cleanCode = trim($rawInput, "* \t\n\r\0\x0B");

        try {
            $transaksi = DB::transaction(function () use ($validated, $karcisHilang, $cleanCode) {
                $query = Transaksi::with(['kendaraan', 'tarif', 'areaParkir'])
                    ->where('status', 'masuk');

                if (! empty($validated['id_parkir'])) {
                    $query->where('id_parkir', (int) $validated['id_parkir']);
                } elseif (preg_match('/^PRK-(\d+)$/i', $cleanCode, $matches)) {
                    $query->where('id_parkir', (int) ltrim($matches[1], '0'));
                } else {
                    $plat = strtoupper($cleanCode);
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
                $biayaParkirNormal = $durasiJam * $tarifPerJam;

                $biayaDenda = 0;

                if ($karcisHilang) {
                    $config = PengaturanParkir::first();
                    $biayaDenda = (float) ($config->denda_karcis_hilang ?? 25000);
                    $skemaDenda = $config->skema_denda ?? 'tambahan';

                    $biayaTotal = $skemaDenda === 'pengganti'
                        ? $biayaDenda
                        : ($biayaParkirNormal + $biayaDenda);
                } else {
                    $biayaTotal = $biayaParkirNormal;
                }

                $trx->update([
                    'waktu_keluar' => $waktuKeluar,
                    'durasi_jam' => $durasiJam,
                    'karcis_hilang' => $karcisHilang,
                    'biaya_denda' => $biayaDenda,
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
                $catatanDenda = $karcisHilang ? ", Denda Rp {$biayaDenda}" : '';
                LogAktivitas::create([
                    'id_user' => auth('api')->id(),
                    'aktivitas' => Str::limit("Keluar {$platNomor} ({$durasiJam}j{$catatanDenda}, Rp {$biayaTotal})", 240, '...'),
                    'status' => 'berhasil',
                    'waktu_aktivitas' => now(),
                ]);

                return $trx->fresh(['kendaraan', 'tarif', 'areaParkir', 'user']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi parkir keluar berhasil diproses',
                'no_transaksi' => 'PRK-' . str_pad((string) $transaksi->id_parkir, 6, '0', STR_PAD_LEFT),
                'data' => $transaksi,
            ], 200);
        } catch (\Throwable $e) {
            $identitas = ! empty($validated['id_parkir'])
                ? "ID #{$validated['id_parkir']}"
                : ($cleanCode !== '' ? strtoupper($cleanCode) : '-');
            $alasanSingkat = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Gagal memproses transaksi keluar';

            LogAktivitas::create([
                'id_user' => auth('api')->id(),
                'aktivitas' => Str::limit("Gagal keluar ({$identitas}): {$alasanSingkat}", 240, '...'),
                'status' => 'gagal',
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $alasanSingkat,
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
                'biaya_denda' => $transaksi->biaya_denda ?? 0,
                'karcis_hilang' => (bool) $transaksi->karcis_hilang,
                'biaya_total' => $transaksi->biaya_total,
                'petugas' => $transaksi->user->nama_lengkap ?? null,
            ],
            'raw' => $transaksi,
        ], 200);
    }
}
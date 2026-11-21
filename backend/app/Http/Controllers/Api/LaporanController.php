<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LaporanController extends Controller
{
    public function pendapatan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'jenis_kendaraan' => 'nullable|string|max:50',
            'id_area' => 'nullable|exists:tb_area_parkir,id_area',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi filter laporan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Transaksi::with(['kendaraan', 'tarif', 'areaParkir', 'user'])
            ->where('status', 'keluar')
            ->whereNotNull('waktu_keluar');

        if ($request->filled('tanggal_mulai')) {
            $start = Carbon::parse($request->tanggal_mulai)->startOfDay();
            $query->where('waktu_keluar', '>=', $start);
        }

        if ($request->filled('tanggal_selesai')) {
            $end = Carbon::parse($request->tanggal_selesai)->endOfDay();
            $query->where('waktu_keluar', '<=', $end);
        }

        if ($request->filled('id_area')) {
            $query->where('id_area', $request->id_area);
        }

        if ($request->filled('jenis_kendaraan')) {
            $jenis = strtolower(trim($request->jenis_kendaraan));
            $query->whereHas('kendaraan', function ($q) use ($jenis) {
                $q->whereRaw('LOWER(jenis_kendaraan) = ?', [$jenis]);
            });
        }

        $transaksiKeluar = $query->orderByDesc('waktu_keluar')->get();

        $totalPendapatan = (float) $transaksiKeluar->sum('biaya_total');
        $jumlahTransaksi = $transaksiKeluar->count();
        $totalDurasiJam = (int) $transaksiKeluar->sum('durasi_jam');

        $perJenisKendaraan = $transaksiKeluar
            ->groupBy(function ($item) {
                return strtolower($item->kendaraan->jenis_kendaraan ?? 'tidak diketahui');
            })
            ->map(function ($group, $jenis) {
                return [
                    'jenis_kendaraan' => $jenis,
                    'jumlah_transaksi' => $group->count(),
                    'total_durasi_jam' => (int) $group->sum('durasi_jam'),
                    'total_pendapatan' => (float) $group->sum('biaya_total'),
                ];
            })
            ->values();

        $perArea = $transaksiKeluar
            ->groupBy(function ($item) {
                return $item->areaParkir->nama_area ?? 'Area Tidak Diketahui';
            })
            ->map(function ($group, $namaArea) {
                return [
                    'nama_area' => $namaArea,
                    'jumlah_transaksi' => $group->count(),
                    'total_pendapatan' => (float) $group->sum('biaya_total'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'filter' => [
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'jenis_kendaraan' => $request->jenis_kendaraan,
                'id_area' => $request->id_area,
            ],
            'ringkasan' => [
                'total_pendapatan' => $totalPendapatan,
                'jumlah_transaksi' => $jumlahTransaksi,
                'total_durasi_jam' => $totalDurasiJam,
            ],
            'breakdown' => [
                'per_jenis_kendaraan' => $perJenisKendaraan,
                'per_area' => $perArea,
            ],
            'data' => $transaksiKeluar,
        ], 200);
    }
}
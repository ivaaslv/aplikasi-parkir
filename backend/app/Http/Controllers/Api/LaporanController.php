<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laporan\LaporanPendapatanRequest;
use App\Models\Transaksi;
use Carbon\Carbon;

class LaporanController extends Controller
{
    public function pendapatan(LaporanPendapatanRequest $request)
    {
        $validated = $request->validated();

        $query = Transaksi::with(['kendaraan', 'tarif', 'areaParkir', 'user'])
            ->where('status', 'keluar')
            ->whereNotNull('waktu_keluar');

        if (! empty($validated['tanggal_mulai'])) {
            $query->where('waktu_keluar', '>=', Carbon::parse($validated['tanggal_mulai'])->startOfDay());
        }

        if (! empty($validated['tanggal_selesai'])) {
            $query->where('waktu_keluar', '<=', Carbon::parse($validated['tanggal_selesai'])->endOfDay());
        }

        if (! empty($validated['id_area'])) {
            $query->where('id_area', $validated['id_area']);
        }

        if (! empty($validated['jenis_kendaraan'])) {
            $jenis = strtolower(trim($validated['jenis_kendaraan']));
            $query->whereHas('kendaraan', function ($q) use ($jenis) {
                $q->whereRaw('LOWER(jenis_kendaraan) = ?', [$jenis]);
            });
        }

        $transaksiKeluar = $query->orderByDesc('waktu_keluar')->get();

        $totalPendapatan = (float) $transaksiKeluar->sum('biaya_total');
        $jumlahTransaksi = $transaksiKeluar->count();
        $totalDurasiJam = (int) $transaksiKeluar->sum('durasi_jam');

        $perJenisKendaraan = $transaksiKeluar
            ->groupBy(fn ($item) => strtolower($item->kendaraan->jenis_kendaraan ?? 'tidak diketahui'))
            ->map(fn ($group, $jenis) => [
                'jenis_kendaraan' => $jenis,
                'jumlah_transaksi' => $group->count(),
                'total_durasi_jam' => (int) $group->sum('durasi_jam'),
                'total_pendapatan' => (float) $group->sum('biaya_total'),
            ])
            ->values();

        $perArea = $transaksiKeluar
            ->groupBy(fn ($item) => $item->areaParkir->nama_area ?? 'Area Tidak Diketahui')
            ->map(fn ($group, $namaArea) => [
                'nama_area' => $namaArea,
                'jumlah_transaksi' => $group->count(),
                'total_pendapatan' => (float) $group->sum('biaya_total'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'filter' => [
                'tanggal_mulai' => $validated['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $validated['tanggal_selesai'] ?? null,
                'jenis_kendaraan' => $validated['jenis_kendaraan'] ?? null,
                'id_area' => $validated['id_area'] ?? null,
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
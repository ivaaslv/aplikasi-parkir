<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LogAktivitasController extends Controller
{
    public function index(Request $request)
    {

        $query = LogAktivitas::with('user')
            ->orderByDesc('waktu_aktivitas')
            ->orderByDesc('id_log');

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->id_user);
        }

        if ($request->filled('tanggal_mulai')) {
            $start = Carbon::parse($request->tanggal_mulai)->startOfDay();
            $query->where('waktu_aktivitas', '>=', $start);
        }

        if ($request->filled('tanggal_selesai')) {
            $end = Carbon::parse($request->tanggal_selesai)->endOfDay();
            $query->where('waktu_aktivitas', '<=', $end);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('aktivitas', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->get();

        return response()->json([
            'success' => true,
            'total' => $logs->count(),
            'data' => $logs,
        ], 200);
    }

    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:tb_log_aktivitas,id_log'],
        ]);

        $deletedCount = LogAktivitas::whereIn('id_log', $validated['ids'])->delete();

        LogAktivitas::create([
            'id_user' => auth('api')->id(),
            'aktivitas' => "Menghapus {$deletedCount} data log aktivitas",
            'status' => 'berhasil',
            'waktu_aktivitas' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} log aktivitas berhasil dihapus",
            'deleted_count' => $deletedCount,
        ], 200);
    }
}
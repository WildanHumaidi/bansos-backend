<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class LogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs->map(function ($log) {
                return [
                    'id'         => $log->id,
                    'user_name'  => $log->user_name,
                    'role'       => $log->role,
                    'aksi'       => $log->aksi,
                    'created_at' => $log->created_at,
                ];
            }),
        ]);
    }

    /**
     * Static helper — dipanggil dari controller lain untuk mencatat aktivitas.
     */
    public static function catatLog(string $user_name, string $role, string $aksi): void
    {
        try {
            $user = auth()->user();
            $prefixWilayah = '';

            // Otomatis menyisipkan stempel wilayah jika yang bergerak adalah RT/RW
            if ($user) {
                if ($user->role === 'rt' && !empty($user->rt)) {
                    // Membuat format konsisten, misal: [RT 003]
                    $prefixWilayah = "[RT " . str_pad($user->rt, 3, '0', STR_PAD_LEFT) . "] ";
                } elseif ($user->role === 'rw') {
                    $prefixWilayah = "[RW 08] ";
                }
            }

            ActivityLog::create([
                'user_name' => $user_name,
                'role'      => $role, // Tetap biarkan murni 'rt' atau 'rw' agar warna badge UI di frontend tidak rusak
                'aksi'      => $prefixWilayah . $aksi,
            ]);
        } catch (\Exception $e) {
            // Fail silently: Jangan sampai error pencatatan log mengganggu fungsi utama aplikasi
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class LogController extends Controller
{
    // Menampilkan seluruh log (Diurutkan dari yang paling baru)
    public function index()
    {
        $logs = ActivityLog::orderBy('created_at', 'desc')->get();
        
        // Format waktu agar lebih mudah dibaca oleh React
        $formattedLogs = $logs->map(function($log) {
            return [
                'id' => $log->id,
                'waktu' => $log->created_at->format('Y-m-d H:i:s'),
                'user' => $log->user_name . ' (' . strtoupper($log->role) . ')',
                'aksi' => $log->aksi
            ];
        });

        return response()->json(['success' => true, 'data' => $formattedLogs]);
    }

    // Fungsi pembantu statis (Bisa dipanggil dari Controller mana saja)
    public static function catatLog($user_name, $role, $aksi)
    {
        ActivityLog::create([
            'user_name' => $user_name,
            'role' => $role,
            'aksi' => $aksi
        ]);
    }
}
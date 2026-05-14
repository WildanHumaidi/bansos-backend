<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    // Mengambil riwayat pesan
    public function getPesan($id_pengajuan)
    {
        $pesan = DB::table('pesan_chat')
            ->where('id_pengajuan', $id_pengajuan)
            ->orderBy('created_at', 'asc')
            ->get();
            
        return response()->json(['success' => true, 'data' => $pesan]);
    }

    // Mengirim pesan baru
    public function storePesan(Request $request, $id_pengajuan)
    {
        // Deteksi siapa yang mengirim berdasarkan token login
        $role = $request->user()->role === 'rt' ? 'rt' : 'warga';
        
        DB::table('pesan_chat')->insert([
            'id_pengajuan' => $id_pengajuan,
            'pengirim_role' => $role,
            'isi_pesan' => $request->isi_pesan,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json(['success' => true, 'message' => 'Pesan terkirim']);
    }
}
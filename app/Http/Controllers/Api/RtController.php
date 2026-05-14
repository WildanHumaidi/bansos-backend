<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RtController extends Controller
{
    public function getPesan($id)
    {
        $pesan = DB::table('pesan_chat')->where('id_pengajuan', $id)->orderBy('created_at', 'asc')->get();
        return response()->json(['success' => true, 'data' => $pesan]);
    }

    public function storePesan(Request $request, $id)
    {
        DB::table('pesan_chat')->insert([
            'id_pengajuan' => $id,
            'pengirim_role' => $request->pengirim_role, // 'rt' atau 'warga'
            'isi_pesan' => $request->isi_pesan,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['success' => true, 'message' => 'Pesan terkirim']);
    }
}
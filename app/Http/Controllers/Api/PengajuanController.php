<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kriteria;
use App\Models\Pengajuan;
use App\Models\Penilaian;
use Illuminate\Support\Facades\DB;

class PengajuanController extends Controller
{
    public function getFormKuesioner()
    {
        
        $kriteria = Kriteria::with('subKriteria')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Data kuesioner berhasil diambil',
            'data' => $kriteria
        ]);
    }

    
    public function submitPengajuan(Request $request)
    {
        $request->validate([
            'id_warga' => 'required|exists:warga,id_warga',
            'jawaban' => 'required|array', 
        ]);

        DB::beginTransaction();
        try {
            
            $pengajuan = Pengajuan::create([
                'id_warga' => $request->id_warga,
                'status' => 'menunggu_verifikasi'
            ]);

            foreach ($request->jawaban as $jawab) {
                Penilaian::create([
                    'id_pengajuan' => $pengajuan->id_pengajuan,
                    'id_kriteria' => $jawab['id_kriteria'],
                    'id_subkriteria' => $jawab['id_subkriteria'],
                ]);
            }

            DB::commit(); 

            return response()->json([
                'success' => true,
                'message' => 'Laporan pengajuan bansos berhasil dikirim!',
                'data' => $pengajuan
            ], 201);

        } catch (\Exception $e) {
            DB::rollback(); 
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }
}
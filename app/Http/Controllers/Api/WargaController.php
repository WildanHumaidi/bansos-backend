<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pengajuan;
use App\Models\Warga;
use Illuminate\Support\Facades\DB;

class WargaController extends Controller
{
    /**
     * Cek status pengajuan warga yang sedang login.
     */
    public function getStatus(Request $request)
    {
        try {
            // Cari data warga berdasarkan nama user yang login
            $warga = DB::table('warga')
                ->where('nama_lengkap', $request->user()->name)
                ->latest()
                ->first();

            if (!$warga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data warga tidak ditemukan'
                ], 404);
            }

            // Ambil pengajuan menggunakan id_warga yang benar
            $pengajuan = DB::table('pengajuan')
                ->where('id_warga', $warga->id_warga)
                ->latest()
                ->first();

            if (!$pengajuan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada data pengajuan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $pengajuan
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function kirimPengajuan(Request $request)
    {
        try {
            $request->validate([
                'alamat'         => 'required',
                'rt'             => 'required',
                'jawaban_mentah' => 'required|array'
            ]);

            $user = Auth::user();

            // Sinkronisasi ke tabel warga
            $warga = Warga::updateOrCreate(
                ['nama_lengkap' => $user->name],
                [
                    'rt'       => $request->rt,
                    'alamat'   => $request->alamat,
                    'rw'       => '08',
                    'password' => bcrypt('123')
                ]
            );

            $idWargaAsli = $warga->id_warga ?? $warga->id;

            // Cek duplikasi pengajuan
            $cekData = Pengajuan::where('id_warga', $idWargaAsli)->first();
            if ($cekData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah pernah mengirimkan pengajuan!'
                ], 400);
            }


            $pengajuan                 = new Pengajuan();
            $pengajuan->id_warga       = $idWargaAsli;
            $pengajuan->jawaban_mentah = json_encode($request->jawaban_mentah);
            $pengajuan->status         = 'menunggu';
            $pengajuan->save();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil dikirim dan siap diverifikasi!'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

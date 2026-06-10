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
     * GET /api/warga/status
     * GET /api/warga/pengajuan/status
     *
     * Cek status pengajuan warga yang sedang login.
     * Dicari berdasarkan nama user → id_warga → pengajuan terbaru.
     */
    public function getStatus(Request $request)
    {
        try {
            $user = $request->user();

            // Cari di tabel warga berdasarkan nama user yang login
            $warga = DB::table('warga')
                ->where('nama_lengkap', $user->name)
                ->latest()
                ->first();

            if (!$warga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data warga tidak ditemukan. Silakan isi kuesioner terlebih dahulu.',
                ], 404);
            }

            $pengajuan = DB::table('pengajuan')
                ->where('id_warga', $warga->id_warga)
                ->latest()
                ->first();

            if (!$pengajuan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada data pengajuan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $pengajuan,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Alias — supaya route lama /warga/status & /warga/pengajuan/status
     * sama-sama mengarah ke getStatus()
     */
    public function cekStatus(Request $request)
    {
        return $this->getStatus($request);
    }

    /**
     * POST /api/warga/pengajuan-rt
     *
     * Dipakai RT untuk input kuesioner atas nama warga.
     * Menerima nama_lengkap, alamat, rt, jawaban_mentah (array).
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama_lengkap'   => 'required|string',
                'alamat'         => 'required|string',
                'rt'             => 'required',
                'jawaban_mentah' => 'required|array',
            ]);

            $warga = Warga::updateOrCreate(
                ['nama_lengkap' => $request->nama_lengkap],
                [
                    'rt'       => $request->rt,
                    'alamat'   => $request->alamat,
                    'rw'       => '08',
                    'password' => bcrypt('123'),
                ]
            );

            $idWarga = $warga->id_warga ?? $warga->id;

            $cek = Pengajuan::where('id_warga', $idWarga)->first();
            if ($cek) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warga ini sudah pernah memiliki pengajuan!',
                ], 400);
            }

            $pengajuan                 = new Pengajuan();
            $pengajuan->id_warga       = $idWarga;
            $pengajuan->jawaban_mentah = json_encode($request->jawaban_mentah);
            $pengajuan->status         = 'menunggu';
            $pengajuan->save();

            return response()->json([
                'success' => true,
                'message' => 'Data kuesioner warga berhasil disimpan!',
                'data'    => $pengajuan,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/warga/pengajuan
     *
     * Dipakai Warga Dashboard — kirim kuesioner sendiri.
     */
    public function kirimPengajuan(Request $request)
    {
        try {
            $request->validate([
                'alamat'         => 'required',
                'rt'             => 'required',
                'jawaban_mentah' => 'required|array',
            ]);

            $user = Auth::user();

            $warga = Warga::updateOrCreate(
                ['nama_lengkap' => $user->name],
                [
                    'rt'       => $request->rt,
                    'alamat'   => $request->alamat,
                    'rw'       => '08',
                    'password' => bcrypt('123'),
                ]
            );

            $idWarga = $warga->id_warga ?? $warga->id;

            $cek = Pengajuan::where('id_warga', $idWarga)->first();
            if ($cek) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah pernah mengirimkan pengajuan!',
                ], 400);
            }

            $pengajuan                 = new Pengajuan();
            $pengajuan->id_warga       = $idWarga;
            $pengajuan->jawaban_mentah = json_encode($request->jawaban_mentah);
            $pengajuan->status         = 'menunggu';
            $pengajuan->save();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil dikirim dan siap diverifikasi!',
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
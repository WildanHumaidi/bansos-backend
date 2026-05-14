<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengajuan;
use App\Models\Kriteria;
use Illuminate\Support\Facades\Http; // <-- Ini penting untuk menembak API Python

class SawController extends Controller
{
    // Fungsi 1: Menyiapkan Data (Sudah kita buat sebelumnya)
    public function siapkanDataUntukPython()
    {
        $pengajuan_valid = Pengajuan::with(['warga', 'penilaian.subKriteria'])
                                    ->where('status', 'disetujui')
                                    ->get();

        if ($pengajuan_valid->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Belum ada data warga yang disetujui RT.'], 404);
        }

        $data_kriteria = Kriteria::all()->map(function($k) {
            return [
                'id_kriteria' => 'C' . $k->id_kriteria,
                'tipe' => $k->tipe,
                'bobot' => $k->bobot
            ];
        });

        $dataset_warga = [];

        foreach ($pengajuan_valid as $p) {
            $baris = [
                'id_pengajuan' => $p->id_pengajuan,
                'nama_warga' => $p->warga->nama_lengkap,
                'alamat' => $p->warga->alamat,
                'rt' => $p->warga->rt,
            ];

            foreach ($p->penilaian as $penilaian) {
                $kode_kriteria = 'C' . $penilaian->id_kriteria;
                $baris[$kode_kriteria] = $penilaian->subKriteria->nilai;
            }

            $dataset_warga[] = $baris;
        }

        return response()->json([
            'success' => true,
            'kriteria' => $data_kriteria,
            'dataset' => $dataset_warga
        ]);
    }

    // Fungsi 2: Jembatan Eksekusi ke Python (BARU)
    public function hitungRanking()
    {
        // 1. Ambil dataset dari fungsi di atas
        $responseDataset = $this->siapkanDataUntukPython();
        
        // Jika data kosong, langsung kembalikan errornya
        if ($responseDataset->getStatusCode() != 200) {
            return $responseDataset;
        }

        $data = $responseDataset->getData(true); // Ekstrak JSON menjadi Array

        // 2. Tembak ke API Python (Port 8001)
        try {
            $pythonResponse = Http::post('http://127.0.0.1:8001/hitung-saw', [
                'kriteria' => $data['kriteria'],
                'dataset' => $data['dataset']
            ]);

            // 3. Kembalikan hasil perhitungan Python ke Frontend (React)
            if ($pythonResponse->successful()) {
                return response()->json($pythonResponse->json());
            } else {
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal menghitung di mesin Python. Cek log Python.'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Mesin Python tidak menyala atau tidak bisa dihubungi: ' . $e->getMessage()
            ], 500);
        }
    }
}
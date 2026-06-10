<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Models\Kriteria;

class SawController extends Controller
{
    /**
     * GET /api/saw/hitung
     *
     * Hitung ranking SAW sepenuhnya di PHP — tidak butuh Python microservice.
     *
     * Alur:
     *  1. Ambil semua pengajuan berstatus 'disetujui' beserta relasi warga & penilaian.
     *  2. Ambil kriteria (bobot + tipe) dari DB.
     *  3. Normalisasi bobot → total = 1.
     *  4. Bangun matriks keputusan dari nilai sub-kriteria tiap warga.
     *  5. Normalisasi matriks (benefit: x/max, cost: min/x).
     *  6. Hitung skor V = Σ(bobot_norm × r_ij).
     *  7. Urutkan descending → kembalikan sebagai JSON.
     */
    public function hitungRanking()
    {
        // ── 1. Ambil data pengajuan ──────────────────────────────────
        $pengajuanList = Pengajuan::with(['warga', 'penilaian.subKriteria'])
            ->where('status', 'disetujui')
            ->get();

        if ($pengajuanList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data warga yang berstatus disetujui.',
            ], 404);
        }

        // ── 2. Ambil kriteria ────────────────────────────────────────
        $kriteriaList = Kriteria::orderBy('id_kriteria')->get();

        if ($kriteriaList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data kriteria tidak ditemukan.',
            ], 404);
        }

        // ── 3. Normalisasi bobot (pastikan total = 1) ────────────────
        $totalBobot = $kriteriaList->sum('bobot');
        if ($totalBobot <= 0) $totalBobot = 1;

        // Map: id_kriteria → ['tipe', 'bobot_norm']
        $kriteriaMap = [];
        foreach ($kriteriaList as $k) {
            $kriteriaMap[$k->id_kriteria] = [
                'nama'       => $k->nama_kriteria,
                'tipe'       => $k->tipe,             // 'benefit' | 'cost'
                'bobot_norm' => $k->bobot / $totalBobot,
            ];
        }

        // ── 4. Bangun matriks keputusan ──────────────────────────────
        // $matriks[id_pengajuan][id_kriteria] = nilai_subkriteria (float)
        $matriks = [];
        $meta    = []; // simpan nama & info warga

        foreach ($pengajuanList as $p) {
            if (!$p->warga) continue;

            $idP = $p->id_pengajuan;
            $meta[$idP] = [
                'id_pengajuan' => $idP,
                'nama_warga'   => $p->warga->nama_lengkap,
                'alamat'       => $p->warga->alamat ?? '',
                'rt'           => $p->warga->rt,
            ];

            foreach ($p->penilaian as $penilaian) {
                if (!$penilaian->subKriteria) continue;
                $matriks[$idP][$penilaian->id_kriteria] = (float) $penilaian->subKriteria->nilai;
            }
        }

        if (empty($matriks)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data penilaian yang valid.',
            ], 404);
        }

        // ── 5. Tentukan max & min per kriteria ───────────────────────
        $maxNilai = [];
        $minNilai = [];

        foreach ($kriteriaMap as $idK => $_) {
            $kolom = array_filter(
                array_column($matriks, $idK),
                fn($v) => is_numeric($v)
            );

            if (empty($kolom)) {
                $maxNilai[$idK] = 1;
                $minNilai[$idK] = 1;
            } else {
                $maxNilai[$idK] = max($kolom);
                $minNilai[$idK] = min($kolom);
            }
        }

        // ── 6. Hitung skor V ─────────────────────────────────────────
        $hasil = [];

        foreach ($matriks as $idP => $nilaiPerKriteria) {
            $skorV = 0.0;

            foreach ($kriteriaMap as $idK => $info) {
                $nilai = $nilaiPerKriteria[$idK] ?? 0;
                $max   = $maxNilai[$idK] ?: 1;
                $min   = $minNilai[$idK] ?: 1;

                // Normalisasi SAW
                if ($info['tipe'] === 'benefit') {
                    $rij = ($max > 0) ? ($nilai / $max) : 0;
                } else {
                    // cost: semakin kecil nilainya, semakin baik
                    $rij = ($nilai > 0) ? ($min / $nilai) : 0;
                }

                $skorV += $info['bobot_norm'] * $rij;
            }

            $hasil[] = array_merge($meta[$idP], [
                'skor_saw' => round($skorV, 6),
            ]);
        }

        // ── 7. Urutkan descending berdasarkan skor ───────────────────
        usort($hasil, fn($a, $b) => $b['skor_saw'] <=> $a['skor_saw']);

        // Tambahkan kolom ranking
        foreach ($hasil as $i => &$row) {
            $row['ranking'] = $i + 1;
        }

        return response()->json([
            'success' => true,
            'source'  => 'php',
            'total'   => count($hasil),
            'data'    => $hasil,
        ]);
    }

    /**
     * GET /api/saw/dataset
     *
     * Endpoint opsional: kembalikan dataset mentah (matriks nilai)
     * tanpa menghitung skor — berguna untuk debug atau export.
     */
    public function getDataset()
    {
        $pengajuanList = Pengajuan::with(['warga', 'penilaian.subKriteria'])
            ->where('status', 'disetujui')
            ->get();

        $kriteriaList = Kriteria::orderBy('id_kriteria')->get();

        $dataset = [];
        foreach ($pengajuanList as $p) {
            if (!$p->warga) continue;

            $baris = [
                'id_pengajuan' => $p->id_pengajuan,
                'nama_warga'   => $p->warga->nama_lengkap,
                'alamat'       => $p->warga->alamat ?? '',
                'rt'           => $p->warga->rt,
            ];

            foreach ($p->penilaian as $penilaian) {
                if (!$penilaian->subKriteria) continue;
                $baris['C' . $penilaian->id_kriteria] = $penilaian->subKriteria->nilai;
            }

            $dataset[] = $baris;
        }

        return response()->json([
            'success'  => true,
            'kriteria' => $kriteriaList->map(fn($k) => [
                'id_kriteria'   => 'C' . $k->id_kriteria,
                'nama_kriteria' => $k->nama_kriteria,
                'tipe'          => $k->tipe,
                'bobot'         => $k->bobot,
            ]),
            'dataset' => $dataset,
        ]);
    }
}
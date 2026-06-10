<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kriteria;
use App\Models\SubKriteria;

class KriteriaController extends Controller
{
    /**
     * GET /api/admin/kriteria
     * Ambil semua kriteria beserta jumlah sub-kriteria.
     */
    public function index()
    {
        $kriteria = Kriteria::withCount('subKriteria')
            ->orderBy('id_kriteria', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $kriteria]);
    }

    /**
     * PUT /api/admin/kriteria/{id}
     * Update nama, tipe, dan bobot kriteria.
     *
     * BUG FIX:
     * 1. Validasi bobot diubah dari max:1 → max:100 (DB simpan nilai 0–100, bukan 0–1)
     * 2. Tambahkan update nama_kriteria yang sebelumnya tidak ada
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_kriteria' => 'sometimes|string|max:255',
            'tipe'          => 'required|in:benefit,cost',
            // BUG FIX: sebelumnya 'max:1' — bobot di DB disimpan 0–100
            'bobot'         => 'required|numeric|min:0|max:100',
        ]);

        $kriteria = Kriteria::where('id_kriteria', $id)->first();
        if (!$kriteria) {
            return response()->json(['success' => false, 'message' => 'Kriteria tidak ditemukan'], 404);
        }

        $data = [
            'tipe'  => $request->tipe,
            'bobot' => $request->bobot,
        ];
        // BUG FIX: update nama jika dikirim
        if ($request->filled('nama_kriteria')) {
            $data['nama_kriteria'] = $request->nama_kriteria;
        }

        $kriteria->update($data);

        LogController::catatLog(
            auth()->user()->name ?? 'sistem',
            auth()->user()->role ?? 'admin',
            "Update kriteria C{$id}: {$kriteria->nama_kriteria}, bobot={$request->bobot}, tipe={$request->tipe}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Kriteria berhasil diperbarui',
            'data'    => $kriteria->fresh(),
        ]);
    }

    /**
     * GET /api/admin/kriteria/{id}/subkriteria
     * Ambil semua sub-kriteria untuk kriteria tertentu.
     * (Endpoint baru yang dibutuhkan AdminDashboard)
     */
    public function getSubKriteria($id)
    {
        $kriteria = Kriteria::where('id_kriteria', $id)->first();
        if (!$kriteria) {
            return response()->json(['success' => false, 'message' => 'Kriteria tidak ditemukan'], 404);
        }

        $subKriteria = SubKriteria::where('id_kriteria', $id)
            ->orderBy('nilai', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $subKriteria,
            'kriteria' => $kriteria,
        ]);
    }

    /**
     * PUT /api/admin/subkriteria/{id}
     * Update keterangan dan nilai sub-kriteria.
     * (Endpoint baru yang dibutuhkan AdminDashboard)
     */
    public function updateSubKriteria(Request $request, $id)
    {
        $request->validate([
            'keterangan' => 'required|string|max:255',
            'nilai'      => 'required|numeric|in:2.5,5,7.5,10',
        ]);

        $sub = SubKriteria::where('id_subkriteria', $id)->first();
        if (!$sub) {
            return response()->json(['success' => false, 'message' => 'Sub-kriteria tidak ditemukan'], 404);
        }

        $sub->update([
            'keterangan' => $request->keterangan,
            'nilai'      => $request->nilai,
        ]);

        LogController::catatLog(
            auth()->user()->name ?? 'sistem',
            auth()->user()->role ?? 'admin',
            "Update sub-kriteria #{$id}: '{$request->keterangan}', nilai={$request->nilai}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Sub-kriteria berhasil diperbarui',
            'data'    => $sub->fresh(),
        ]);
    }
}
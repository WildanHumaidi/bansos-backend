<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kriteria;

class KriteriaController extends Controller
{
    // Mengambil semua data kriteria
    public function index()
    {
        $kriteria = Kriteria::orderBy('id_kriteria', 'asc')->get();
        return response()->json(['success' => true, 'data' => $kriteria]);
    }

    // Mengupdate Bobot dan Tipe Kriteria
    public function update(Request $request, $id)
    {
        $request->validate([
            'bobot' => 'required|numeric|min:0|max:1',
            'tipe' => 'required|in:benefit,cost'
        ]);

        $kriteria = Kriteria::where('id_kriteria', $id)->first();
        if (!$kriteria) {
            return response()->json(['success' => false, 'message' => 'Kriteria tidak ditemukan'], 404);
        }

        $kriteria->update([
            'bobot' => $request->bobot,
            'tipe' => $request->tipe
        ]);

        return response()->json(['success' => true, 'message' => 'Kriteria berhasil diperbarui']);
    }
}
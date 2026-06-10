<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengajuan;
use App\Models\PengajuanDetail;
use App\Models\Warga;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminRtController extends Controller
{
    // ==========================================
    // BAGIAN 1: MANAJEMEN USER SISTEM
    // ==========================================

    public function getUsers()
    {
        $users = DB::table('users')->get();

        $usersDenganAlamat = $users->map(function ($user) {
            $alamatAsli = '';
            try {
                if (!empty($user->alamat)) {
                    $alamatAsli = $user->alamat;
                } else {
                    // Fallback: cari di tabel warga
                    $warga = DB::table('warga')
                        ->where('nama_lengkap', $user->name)
                        ->first();
                    if ($warga && !empty($warga->alamat)) {
                        $alamatAsli = $warga->alamat;
                    }
                }
            } catch (\Exception $e) { /* silent */ }

            $user->alamat = $alamatAsli;
            return $user;
        });

        return response()->json(['success' => true, 'data' => $usersDenganAlamat]);
    }

    // BUG FIX: tambahkan 'alamat' yang sebelumnya tidak disimpan
    public function storeUser(Request $request)
    {
        try {
            $request->validate([
                'name'     => 'required|string',
                'username' => 'required|unique:users',
                'password' => 'required|min:6',
                'role'     => 'required|in:admin,rw,rt,warga',
                'rt'       => 'nullable|string',
                'alamat'   => 'nullable|string',
            ]);

            $user = User::create([
                'name'     => $request->name,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role'     => $request->role,
                'rt'       => $request->rt,
                'alamat'   => $request->alamat ?? '', // BUG FIX: sebelumnya tidak ada
            ]);

            LogController::catatLog(
                auth()->user()->name ?? 'sistem',
                auth()->user()->role ?? 'admin',
                "Tambah akun baru: {$user->name} (role: {$user->role})"
            );

            return response()->json(['success' => true, 'message' => 'User berhasil ditambahkan!', 'data' => $user]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $request->validate([
                'name'     => 'required|string',
                'username' => 'required|unique:users,username,' . $id,
                'role'     => 'required|in:admin,rw,rt,warga',
                'rt'       => 'nullable|string',
                'alamat'   => 'nullable|string',
            ]);

            $user->name     = $request->name;
            $user->username = $request->username;
            $user->role     = $request->role;
            $user->rt       = $request->rt;
            $user->alamat   = $request->alamat ?? $user->alamat;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            $user->save();

            LogController::catatLog(
                auth()->user()->name ?? 'sistem',
                auth()->user()->role ?? 'admin',
                "Update akun: {$user->name} (role: {$user->role})"
            );

            return response()->json(['success' => true, 'message' => 'Data User berhasil diperbarui!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $nama = $user->name;
            $user->delete();

            LogController::catatLog(
                auth()->user()->name ?? 'sistem',
                auth()->user()->role ?? 'admin',
                "Hapus akun: {$nama}"
            );

            return response()->json(['success' => true, 'message' => 'User berhasil dihapus!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // BAGIAN 2: MANAJEMEN PENGAJUAN & KUESIONER
    // ==========================================

    public function getDaftarPengajuan(Request $request)
    {
        try {
            $user  = $request->user();
            $query = Pengajuan::with(['warga', 'penilaian.subKriteria']);

            if ($user->role === 'rt') {
                $rtNum = (int) $user->rt;
                $query->whereHas('warga', function ($q) use ($rtNum) {
                    $q->where('rt', $rtNum)
                      ->orWhere('rt', str_pad($rtNum, 3, '0', STR_PAD_LEFT));
                });
            }

            $pengajuan = $query->orderBy('created_at', 'desc')->get();
            return response()->json(['success' => true, 'data' => $pengajuan]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ubahStatusPengajuan(Request $request, $id)
    {
        try {
            $pengajuan = Pengajuan::findOrFail($id);
            $pengajuan->update(['status' => $request->status]);

            LogController::catatLog(
                auth()->user()->name ?? 'sistem',
                auth()->user()->role ?? 'rt',
                "Ubah status pengajuan #{$id} menjadi: {$request->status}"
            );

            return response()->json(['success' => true, 'message' => 'Status berhasil diubah']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateKuesioner(Request $request, $id)
    {
        try {
            $pengajuan = Pengajuan::findOrFail($id);

            if ($request->has('jawaban_mentah')) {
                $mentah = is_array($request->jawaban_mentah)
                    ? json_encode($request->jawaban_mentah)
                    : $request->jawaban_mentah;
                $pengajuan->update(['jawaban_mentah' => $mentah]);
            }

            if ($request->has('jawaban') && is_array($request->jawaban)) {
                PengajuanDetail::where('id_pengajuan', $id)->delete();
                foreach ($request->jawaban as $id_kriteria => $id_sub) {
                    PengajuanDetail::create([
                        'id_pengajuan'   => $id,
                        'id_kriteria'    => (int) $id_kriteria,
                        'id_subkriteria' => (int) $id_sub,
                    ]);
                }
            }

            return response()->json(['success' => true, 'message' => 'Kuesioner berhasil diperbarui']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroyPengajuan($id)
    {
        try {
            Pengajuan::destroy($id);
            PengajuanDetail::where('id_pengajuan', $id)->delete();

            LogController::catatLog(
                auth()->user()->name ?? 'sistem',
                auth()->user()->role ?? 'admin',
                "Hapus data kuesioner/pengajuan #{$id}"
            );

            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // BAGIAN 3: IMPORT DATA CSV
    // ==========================================

    public function importCsv(Request $request)
    {
        ini_set('max_execution_time', 300);

        try {
            $request->validate(['file' => 'required|file']);
            $path = $request->file('file')->getRealPath();
            $file = fopen($path, 'r');
            if (!$file) return response()->json(['success' => false, 'message' => 'Gagal membaca file.']);

            // Deteksi delimiter otomatis
            $line      = fgets($file);
            $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            rewind($file);
            fgetcsv($file, 0, $delimiter); // skip header

            $count = 0;
            $passUser  = Hash::make('warga123');
            $passWarga = bcrypt('123');

            DB::beginTransaction();

            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                if (!isset($row[1]) || trim($row[1]) === '') continue;

                $nama   = trim($row[1]);
                $alamat = isset($row[2]) ? trim($row[2]) : '';
                $rtNum  = (int) ($row[13] ?? 1);
                $username = strtolower(preg_replace('/[^a-z0-9]/i', '', $nama)) . rand(10, 99);

                // 1. Buat/ambil user di tabel users
                $user = User::firstOrCreate(
                    ['name' => $nama, 'rt' => $rtNum],
                    ['username' => $username, 'password' => $passUser, 'role' => 'warga', 'alamat' => $alamat]
                );
                // Selalu update alamat
                if (!$user->wasRecentlyCreated) {
                    $user->update(['alamat' => $alamat]);
                }

                // 2. Buat/update entri di tabel warga
                $warga = Warga::updateOrCreate(
                    ['nama_lengkap' => $nama],
                    ['rt' => $rtNum, 'alamat' => $alamat, 'rw' => '8', 'password' => $passWarga]
                );
                $idWarga = $warga->id_warga ?? $warga->id;

                $jawaban = [
                    'q1'  => $row[3]  ?? '-', 'q2'  => $row[4]  ?? '-',
                    'q3'  => $row[5]  ?? '-', 'q4'  => $row[6]  ?? '-',
                    'q5'  => $row[7]  ?? '-', 'q6'  => $row[8]  ?? '-',
                    'q7'  => $row[9]  ?? '-', 'q8'  => $row[10] ?? '-',
                    'q9'  => $row[11] ?? '-', 'q10' => $row[12] ?? '-',
                ];

                // BUG FIX: hapus 'rt' dari pengajuan — kolom itu tidak ada di tabel pengajuan
                Pengajuan::updateOrCreate(
                    ['id_warga' => $idWarga],
                    [
                        'status'        => 'disetujui',
                        'jawaban_mentah' => json_encode($jawaban),
                    ]
                );

                $count++;
            }

            fclose($file);
            DB::commit();

            LogController::catatLog(
                auth()->user()->name ?? 'sistem',
                auth()->user()->role ?? 'admin',
                "Import CSV: {$count} data warga berhasil diproses"
            );

            return response()->json([
                'success' => true,
                'message' => "Berhasil! {$count} data warga tersimpan.",
            ]);

        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage() . ' (baris ' . $e->getLine() . ')',
            ], 500);
        }
    }

    // ==========================================
    // BAGIAN 4: SAPU AJAIB – PERBAIKI DATA MENTAH + GENERATE MATRIKS
    // ==========================================

    public function fixDataMentah()
    {
        $semuaPengajuan = Pengajuan::all();

        $daftarOpsi = [
            'q1'  => ['> 3 KK', '3 KK', '2 KK', '1 KK'],
            'q2'  => ['>= 6 ORANG', '5 ORANG', '4 ORANG', '<= 3 ORANG'],
            'q3'  => ['TIDAK SEKOLAH', 'SEKOLAH DASAR (SD)', 'SEKOLAH MENENGAH PERTAMA (SMP)', 'SMA / SARJANA'],
            'q4'  => ['>= 3 ORANG', '2 ORANG', '1 ORANG', 'TIDAK ADA'],
            'q5'  => ['< Rp. 1.500.000', 'Rp. 1.500.000 - Rp. 3.000.000', 'RP. 3.000.000 - Rp. 5.000.000', '> RP. 5.000.000'],
            'q6'  => ['< Rp. 1.500.000', 'Rp. 1.500.000 - Rp. 3.000.000', 'RP. 3.000.000 - Rp. 5.000.000', '> RP. 5.000.000'],
            'q7'  => ['MENUMPANG', 'SEWA / KONTRAK', 'MILIK ORANG TUA/ SAUDARA /KELUARGA', 'MILIK SENDIRI'],
            'q8'  => ['SUNGAI / MATA AIR', 'SUMUR (BOR/GALI)', 'PDAM', 'KEMASAN / ISI ULANG'],
            'q9'  => ['TIDAK ADA / LAMPU TEMPEL', 'LISTRIK 450 WATT', 'LISTRIK 900 WATT', 'LISTRIK  > 900 WATT'],
            'q10' => ['JALAN KAKI / SEPEDA / SEPEDA MOTOR SEADANYA / TRANSPORTASI UMUM', 'SEPEDA MOTOR 1 UNIT KONDISI BAIK', 'SEPEDA MOTOR LEBIH DARI 1 UNIT DALAM KONDISI BAIK', 'MOBIL'],
        ];

        $count = 0;
        foreach ($semuaPengajuan as $p) {
            $mentah = is_string($p->jawaban_mentah) ? json_decode($p->jawaban_mentah, true) : $p->jawaban_mentah;
            if (!is_array($mentah)) continue;

            $idPengajuan = $p->id_pengajuan ?? $p->id;
            PengajuanDetail::where('id_pengajuan', $idPengajuan)->delete();

            $mentahBaru = [];
            for ($i = 1; $i <= 10; $i++) {
                $key         = "q{$i}";
                $jawabanUser = strtolower(preg_replace('/\s+/', '', $mentah[$key] ?? ''));
                $indexOpsi   = 2; // default ke tengah

                foreach ($daftarOpsi[$key] as $idx => $opsiBenar) {
                    if ($jawabanUser === strtolower(preg_replace('/\s+/', '', $opsiBenar))) {
                        $indexOpsi = $idx;
                        break;
                    }
                }

                $mentahBaru[$key] = $daftarOpsi[$key][$indexOpsi];

                PengajuanDetail::create([
                    'id_pengajuan'   => $idPengajuan,
                    'id_kriteria'    => $i,
                    'id_subkriteria' => (($i - 1) * 4) + $indexOpsi + 1,
                ]);
            }

            $p->jawaban_mentah = json_encode($mentahBaru);
            $p->save();
            $count++;
        }

        LogController::catatLog(
            auth()->user()->name ?? 'sistem',
            auth()->user()->role ?? 'admin',
            "Sapu ajaib: {$count} data kuesioner diperbaiki & matriks di-generate"
        );

        return response()->json([
            'success' => true,
            'message' => "{$count} data diperbaiki dan matriks berhasil di-generate!",
        ]);
    }
}
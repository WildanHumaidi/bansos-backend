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
        // 1. Ambil semua data akun dari tabel users
        $users = DB::table('users')->get();

        // 2. Radar Pencari Alamat
        $usersDenganAlamat = $users->map(function ($user) {
            
            // Teks default jika warga memang benar-benar belum mengisi alamat
            $alamatAsli = 'Belum Mengisi Data';

            try {
                // Cek 1: Apakah alamat ada di tabel 'users'?
                if (isset($user->alamat) && $user->alamat != null && $user->alamat != '') {
                    $alamatAsli = $user->alamat;
                } else {
                    
                    // Cek 2: Cari di tabel 'warga' (Biasanya hasil CSV masuk ke sini)
                    $warga = DB::table('warga')
                        ->where('name', $user->name)
                        ->orWhere('nama_lengkap', $user->name) // Coba nama kolom lain
                        ->first();

                    if ($warga && isset($warga->alamat) && $warga->alamat != '') {
                        $alamatAsli = $warga->alamat;
                    } else {
                        
                        // Cek 3: Cari di tabel 'pengajuan' sebagai opsi terakhir
                        $pengajuan = DB::table('pengajuan')
                            ->where('nama_warga', $user->name) // Pakai nama_warga sesuai struktur umum
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($pengajuan && isset($pengajuan->alamat) && $pengajuan->alamat != '') {
                            $alamatAsli = $pengajuan->alamat;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Sabuk pengaman: biarkan radar mengabaikan jika ada tabel/kolom yang tidak cocok
            }

            // Pasangkan alamat yang ditemukan ke user
            $user->alamat = $alamatAsli;
            return $user;
        });

        return response()->json([
            'success' => true, 
            'data' => $usersDenganAlamat
        ]);
    }
    public function storeUser(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'username' => 'required|unique:users', 
                'password' => 'required',
                'role' => 'required'
            ]);

            $user = new User();
            $user->name = $request->name;
            $user->username = $request->username;
            $user->password = Hash::make($request->password); 
            $user->role = $request->role;
            $user->rt = $request->rt; 
            $user->save();

            return response()->json(['success' => true, 'message' => 'User berhasil ditambahkan!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $request->validate([
                'name' => 'required',
                'username' => 'required|unique:users,username,' . $id, 
                'role' => 'required'
            ]);

            $user->name = $request->name;
            $user->username = $request->username;
            $user->role = $request->role;
            $user->rt = $request->rt;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            $user->save();

            return response()->json(['success' => true, 'message' => 'Data User berhasil diperbarui!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            User::findOrFail($id)->delete();
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
            $user = $request->user();
            $query = Pengajuan::with(['warga', 'penilaian.subKriteria']);

            if ($user->role === 'rt') {
                $query->whereHas('warga', function($q) use ($user) {
                    $q->where('rt', (int) $user->rt)
                      ->orWhere('rt', str_pad($user->rt, 3, '0', STR_PAD_LEFT));
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
        Pengajuan::findOrFail($id)->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }

    public function updateKuesioner(Request $request, $id)
    {
        try {
            $pengajuan = Pengajuan::findOrFail($id);
            if ($request->has('jawaban_mentah')) {
                $pengajuan->update(['jawaban_mentah' => $request->jawaban_mentah]);
            }
            if ($request->has('jawaban') && is_array($request->jawaban)) {
                PengajuanDetail::where('id_pengajuan', $id)->delete();
                foreach ($request->jawaban as $id_kriteria => $nilai_sub) {
                    PengajuanDetail::create([
                        'id_pengajuan'   => $id,
                        'id_kriteria'    => (int)$id_kriteria,
                        'id_subkriteria' => (int)$nilai_sub 
                    ]);
                }
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroyPengajuan($id) 
    {
        Pengajuan::destroy($id);
        return response()->json(['success' => true]);
    }

    // ==========================================
    // BAGIAN 3: IMPORT DATA CSV 
    // ==========================================
    public function importCsv(Request $request)
    {
        ini_set('max_execution_time', 300); 
        ini_set('auto_detect_line_endings', TRUE); 

        try {
            $request->validate(['file' => 'required|file']);
            $path = $request->file('file')->getRealPath();
            
            $file = fopen($path, 'r');
            if (!$file) return response()->json(['success' => false, 'message' => 'Gagal membaca isi file.']);

            $line = fgets($file);
            $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            rewind($file); 
            
            fgetcsv($file, 0, $delimiter); 
            $count = 0;

            $passwordUserDefault = Hash::make('warga123');
            $passwordWargaDefault = bcrypt('123');

            DB::beginTransaction();

            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                if (!isset($row[1]) || trim($row[1]) === '') continue;

                $namaWarga = trim($row[1]);
                $alamatWarga = isset($row[2]) ? trim($row[2]) : '-'; // Simpan alamat dari CSV ke variabel
                $rtWarga = (int)($row[13] ?? 1);

                $usernameOtomatis = strtolower(preg_replace('/[^a-z0-9]/i', '', $namaWarga)) . rand(10, 99);
                
                // 1. Buat akun User jika belum ada (Password tidak akan keriset jika user sudah ada)
                $user = User::firstOrCreate(
                    ['name' => $namaWarga, 'rt' => $rtWarga],
                    ['username' => $usernameOtomatis, 'password' => $passwordUserDefault, 'role' => 'warga']
                );

                // 2. JALUR KHUSUS ALAMAT: Langsung simpan/update alamat ke tabel users
                $user->update(['alamat' => $alamatWarga]);

                $warga = Warga::updateOrCreate(
                    ['nama_lengkap' => $namaWarga, 'rt' => $rtWarga],
                    ['alamat' => $alamatWarga, 'rw' => '8', 'password' => $passwordWargaDefault]
                );

                $idWargaAsli = $warga->id_warga ?? $warga->id;

                $jawabanMentahArray = [
                    'q1' => $row[3] ?? '-',  'q2' => $row[4] ?? '-',
                    'q3' => $row[5] ?? '-',  'q4' => $row[6] ?? '-',
                    'q5' => $row[7] ?? '-',  'q6' => $row[8] ?? '-',
                    'q7' => $row[9] ?? '-',  'q8' => $row[10] ?? '-',
                    'q9' => $row[11] ?? '-', 'q10' => $row[12] ?? '-'
                ];

                Pengajuan::updateOrCreate(
                    ['id_warga' => $idWargaAsli], 
                    [
                        'status' => 'disetujui', 
                        'rt' => $rtWarga,
                        'jawaban_mentah' => json_encode($jawabanMentahArray)
                    ]
                );
                $count++;
            }
            
            fclose($file);
            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => "Sempurna! $count data warga tersimpan secepat kilat dan alamat langsung ter-update."
            ]);

        } catch (\Throwable $e) { 
            DB::rollback();
            return response()->json([
                'success' => false, 
                'message' => 'SERVER FATAL ERROR: ' . $e->getMessage() . ' (Di baris ' . $e->getLine() . ')'
            ], 500);
        }
    }

    // ==========================================
    // BAGIAN 4: SAPU AJAIB (FIXER TEXT + GENERATE MATRIKS)
    // ==========================================
    public function fixDataMentah() 
    {
        $semuaPengajuan = \App\Models\Pengajuan::all();

        $daftarOpsi = [
            'q1' => ["> 3 KK", "3 KK", "2 KK", "1 KK"], 
            'q2' => [">= 6 ORANG", "5 ORANG", "4 ORANG", "<= 3 ORANG"],
            'q3' => ["TIDAK SEKOLAH", "SEKOLAH DASAR (SD)", "SEKOLAH MENENGAH PERTAMA (SMP)", "SMA / SARJANA"],
            'q4' => [">= 3 ORANG", "2 ORANG", "1 ORANG", "TIDAK ADA"],
            'q5' => ["< Rp. 1.500.000", "Rp. 1.500.000 - Rp. 3.000.000", "RP. 3.000.000 - Rp. 5.000.000", "> RP. 5.000.000"],
            'q6' => ["< Rp. 1.500.000", "Rp. 1.500.000 - Rp. 3.000.000", "RP. 3.000.000 - Rp. 5.000.000", "> RP. 5.000.000"],
            'q7' => ["MENUMPANG", "SEWA / KONTRAK", "MILIK ORANG TUA/ SAUDARA /KELUARGA", "MILIK SENDIRI"],
            'q8' => ["SUNGAI / MATA AIR", "SUMUR (BOR/GALI)", "PDAM", "KEMASAN / ISI ULANG"],
            'q9' => ["TIDAK ADA / LAMPU TEMPEL", "LISTRIK 450 WATT", "LISTRIK 900 WATT", "LISTRIK  > 900 WATT"],
            'q10'=> ["JALAN KAKI / SEPEDA / SEPEDA MOTOR SEADANYA / TRANSPORTASI UMUM", "SEPEDA MOTOR 1 UNIT KONDISI BAIK", "SEPEDA MOTOR LEBIH DARI 1 UNIT DALAM KONDISI BAIK", "MOBIL"]
        ];

        $count = 0;
        foreach ($semuaPengajuan as $p) {
            $mentah = is_string($p->jawaban_mentah) ? json_decode($p->jawaban_mentah, true) : $p->jawaban_mentah;
            $mentahBaru = [];

            $idPengajuan = $p->id_pengajuan ?? $p->id;

            // Bersihkan matriks lama (jika ada) agar tidak bentrok atau ganda
            \App\Models\PengajuanDetail::where('id_pengajuan', $idPengajuan)->delete();

            if(is_array($mentah)) {
                for ($i = 1; $i <= 10; $i++) {
                    $key = "q$i";
                    $jawabanUser = isset($mentah[$key]) ? strtolower(preg_replace('/\s+/', '', $mentah[$key])) : '';

                    $ketemu = false;
                    $indexOpsi = 2; 

                    foreach($daftarOpsi[$key] as $idx => $opsiBenar) {
                        $opsiBersih = strtolower(preg_replace('/\s+/', '', $opsiBenar));
                        if($jawabanUser === $opsiBersih) {
                            $mentahBaru[$key] = $opsiBenar;
                            $indexOpsi = $idx;
                            $ketemu = true;
                            break;
                        }
                    }

                    if(!$ketemu) {
                        $mentahBaru[$key] = $daftarOpsi[$key][$indexOpsi];
                    }

                    // Tanamkan ID Subkriteria ke Database agar Mesin SAW bisa membaca
                    $idSub = (($i - 1) * 4) + $indexOpsi + 1;

                    \App\Models\PengajuanDetail::create([
                        'id_pengajuan'   => $idPengajuan,
                        'id_kriteria'    => $i,
                        'id_subkriteria' => $idSub
                    ]);
                }

                $p->jawaban_mentah = json_encode($mentahBaru);
                $p->save();
                $count++;
            }
        }
        return response()->json(['success' => true, 'message' => "Sempurna! $count Data Teks diperbaiki dan Angka Matriks berhasil disuntikkan ke Database!"]);
    }
}
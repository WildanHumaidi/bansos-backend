<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warga;
use App\Models\Pengajuan;
use App\Models\Penilaian;
use App\Models\Kriteria;
use App\Models\SubKriteria;

class ImportWargaSeeder extends Seeder
{
    public function run()
    {
        
        $indeks_nama = 1; 
        $indeks_alamat = 2; 
        $indeks_awal_jawaban = 3; 
        $indeks_rt = 13; 

        $csvFile = fopen(base_path("database/data/data_warga.csv"), "r");
        $firstline = true;
        
        
        while (($baris = fgetcsv($csvFile, 2000, ";")) !== FALSE) {
            
            if ($firstline) {
                $firstline = false;
                continue; 
            }

            
            if (count($baris) < 13) {
                continue; 
            }
                
            
            $angka_rt = (int) ($baris[$indeks_rt] ?? 1); 
            $format_rt = '00' . $angka_rt;

            $warga = Warga::create([
                'nama_lengkap' => $baris[$indeks_nama] ?? 'Tanpa Nama',
                'alamat' => $baris[$indeks_alamat] ?? 'Tanpa Alamat',
                'rt' => $format_rt, 
                'rw' => '008', 
                'password' => bcrypt('12345678') 
            ]);

            
            $pengajuan = Pengajuan::create([
                'id_warga' => $warga->id_warga,
                'status' => 'disetujui'
            ]);

            
            $kriteria_list = Kriteria::orderBy('id_kriteria', 'asc')->get();
            $indeks_jawaban_saat_ini = $indeks_awal_jawaban;

            foreach ($kriteria_list as $kriteria) {
                $teks_jawaban_csv = isset($baris[$indeks_jawaban_saat_ini]) ? trim($baris[$indeks_jawaban_saat_ini]) : '';

                
                $subkriteria = SubKriteria::where('id_kriteria', $kriteria->id_kriteria)
                    ->where('keterangan', 'LIKE', '%' . $teks_jawaban_csv . '%')
                    ->first();

                
                $id_sub = $subkriteria ? $subkriteria->id_subkriteria : SubKriteria::where('id_kriteria', $kriteria->id_kriteria)->orderBy('nilai', 'asc')->first()->id_subkriteria;

                Penilaian::create([
                    'id_pengajuan' => $pengajuan->id_pengajuan,
                    'id_kriteria' => $kriteria->id_kriteria,
                    'id_subkriteria' => $id_sub
                ]);

                $indeks_jawaban_saat_ini++;
            }
        }
        
        fclose($csvFile);
    }
}
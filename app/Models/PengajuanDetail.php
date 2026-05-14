<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanDetail extends Model
{
    protected $table = 'penilaian'; 
    
    
    protected $primaryKey = 'id_penilaian'; 
    
    
    public $timestamps = true; 
    
    protected $fillable = [
        'id_pengajuan',
        'id_kriteria',
        'id_subkriteria' 
    ];

    public function pengajuan()
    {
        return $this->belongsTo(Pengajuan::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function subKriteria()
    {
        return $this->belongsTo(SubKriteria::class, 'id_subkriteria', 'id_subkriteria');
    }
}
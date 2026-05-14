<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengajuan extends Model
{
    protected $table = 'pengajuan'; 
    protected $primaryKey = 'id_pengajuan';
    protected $fillable = ['id_warga', 'status', 'jawaban_mentah'];


    public function warga() {
        return $this->belongsTo(Warga::class, 'id_warga', 'id_warga');
    }

    public function penilaian() {
        return $this->hasMany(PengajuanDetail::class, 'id_pengajuan', 'id_pengajuan');
    }
}
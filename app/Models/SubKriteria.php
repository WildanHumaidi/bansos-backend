<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubKriteria extends Model
{
    protected $table      = 'sub_kriteria';
    protected $primaryKey = 'id_subkriteria';

    protected $fillable = [
        'id_kriteria',
        'keterangan',
        'nilai',
    ];

    protected $casts = [
        'nilai' => 'float',
    ];

    public function kriteria()
    {
        return $this->belongsTo(Kriteria::class, 'id_kriteria', 'id_kriteria');
    }
}
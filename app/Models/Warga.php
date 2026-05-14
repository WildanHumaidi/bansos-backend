<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warga extends Model
{
    
    protected $table = 'warga'; 
    protected $primaryKey = 'id_warga'; 
    
    
    protected $fillable = [
        'nama_lengkap', 
        'alamat', 
        'rt', 
        'rw', 
        'password'
    ];
}
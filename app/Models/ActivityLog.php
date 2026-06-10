<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_name',
        'role',
        'aksi',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
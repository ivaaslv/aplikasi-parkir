<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanParkir extends Model
{
    protected $table = 'tb_pengaturan_parkir';
    protected $primaryKey = 'id_pengaturan';

    protected $fillable = [
        'denda_karcis_hilang',
        'skema_denda',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Showtime extends Model
{
    use HasFactory;

    protected $table = 'seats';

    protected $fillable = [
        'ngay_chieu',
        'thoi_luong_chieu',
        'phim_id',
        'rapphim_id',
        'room_id',
    ];
}

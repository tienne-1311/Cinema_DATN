<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vouchers';

    protected $fillable = [
        'user_id',
        'ma_giam_gia',
        'muc_giam_gia',
        'mota',
        'ngay_het_han',
        'so_luong',
        'so_luong_da_su_dung',
        'trang_thai'
    ];

    protected $dates = ['deleted_at'];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountdownVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'magiamgia_id',
        'ngay',
        'thoi_gian_bat_dau',
        'thoi_gian_ket_thuc',
        'so_luong',
        'so_luong_con_lai',
        'trang_thai',
    ];
    protected static function booted()
    {
        static::creating(function ($coupons) {
            if (is_null($coupons->so_luong_con_lai)) {
                $coupons->so_luong_con_lai = $coupons->so_luong;
            }
        });
    }
    public function coupons()
    {
        return $this->belongsTo(Coupon::class, 'magiamgia_id');
    }
    public function couponCodeTakens()
    {
        return $this->hasMany(CouponCodeTaken::class, 'countdownvoucher_id');
    }
}

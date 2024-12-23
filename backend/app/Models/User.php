<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\VerifyEmail;
use Laravel\Passport\HasApiTokens as PassportHasApiTokens;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, MustVerifyEmailTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = [
        'ho_ten',
        'anh',
        'gioi_tinh',
        'email',
        'so_dien_thoai',
        'password',
        'vai_tro',
        'diem_thuong',
        'ma_giam_gia',
        'so_luot_quay',

    ];

    protected $dates = ['deleted_at'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function couponCodeTakens()
    {
        return $this->hasMany(CouponCodeTaken::class, 'user_id');
    }
    // methods JWT tra ve token khi dang nhap
    //  xác thực JWT JSON Web Token trong Laravel khi sử dụng gói jwt-auth

    //getJWTIdentifier() sẽ lấy giá trị của khóa chính id của người dùng và đưa nó vào token
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // public function getKey()
    // {
    //     return $this->attributes['id'];
    // }

    // trả về giá trị của khóa chính của bản ghi hiện tại id 
    public function getJWTCustomClaims()
    {
        return ['vai_tro' => $this->vai_tro];
    }




    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // bam mk
    ];
    //T thêm khoá

    // không dc sửa cái này
    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }



    // không dc sửa cái này
    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return ! is_null($this->email_verified_at);
    }

    // không dc sửa cái này
    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }

    public function sendEmailVerificationNotification($otp = null)
    {
        $this->notify(new VerifyEmail($otp));
    }
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
    public function registerMember()
    {
        return $this->hasOne(RegisterMember::class, 'user_id'); // Assuming 'user_id' is the foreign key
    }
}

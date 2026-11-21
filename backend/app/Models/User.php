<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $table = 'tb_user';
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama_lengkap',
        'username',
        'password',
        'role',
        'status_aktif',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    public function kendaraan()
    {
        return $this->hasMany(Kendaraan::class, 'id_user', 'id_user');
    }

    public function transaksi() 
    {
        return $this->hasMany(Transaksi::class, 'id_user', 'id_user');
    }

    public function logAktivitas()
    {
        return $this->hasMany(LogAktivitas::class, 'id_user', 'id_user');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'username' => $this->username,
        ];
    }
}

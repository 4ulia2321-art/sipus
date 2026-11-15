<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Tabel pengguna pada database SIPUS.
     *
     * @var string
     */
    protected $table = 't_user';

    /**
     * Primary key tabel t_user.
     *
     * @var string
     */
    protected $primaryKey = 'id_user';

    /**
     * t_user tidak memakai kolom created_at/updated_at Eloquent.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Kolom yang boleh diisi melalui model.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'password',
        'role',
    ];

    /**
     * Jangan pernah mengirim hash password ke tampilan/JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Password disimpan sebagai hash (bcrypt) oleh Laravel.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}

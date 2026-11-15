<?php

namespace App\Models;

/**
 * Alias lama untuk model User.
 *
 * Model ini dipertahankan agar kode/tests lama yang masih memakai TUser
 * tetap berjalan. Keduanya membaca tabel t_user yang sama.
 *
 * @deprecated Gunakan App\Models\User.
 */
class TUser extends User
{
}

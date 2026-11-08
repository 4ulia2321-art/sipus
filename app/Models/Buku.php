<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Buku extends Model
{
    protected $table = 'buku'; // Sesuaikan dengan nama tabel asli di database
    protected $primaryKey = 'id_buku';

    protected $fillable = [
        'id_kategori', 'no_seri_buku', 'judul', 'cover',
        'pengarang', 'penerbit', 'tahun_terbit', 'kategori',
        'lokasi_rak', 'stok'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriBuku extends Model
{
   protected $table = 'kategori_buku';
    protected $primaryKey = 'id_kategori';

    protected $fillable = [
        'nama_kategori', 'keterangan'
    ];

    public function bukus()
    {
        return $this->hasMany(Buku::class, 'id_kategori', 'id_kategori');
    }
}

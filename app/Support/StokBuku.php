<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ringkasan stok buku dalam satuan salinan (copy), bukan jumlah judul.
 *
 * Nilai "stok" pada tabel buku adalah salinan yang masih tersedia.
 * Salinan yang sedang dipinjam disimpan pada detail_peminjaman dan
 * stoknya sudah dikurangi saat peminjaman disetujui, sehingga total
 * koleksi = stok tersedia + salinan yang sedang dipinjam.
 */
class StokBuku
{
    /**
     * @return array{total:int, tersedia:int, dipinjam:int}
     */
    public static function ringkasan(): array
    {
        $tersedia = (int) DB::table('buku')->sum('stok');

        $dipinjam = (int) DB::table('peminjaman')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->whereIn('peminjaman.status', ['Dipinjam', 'Terlambat'])
            ->whereNull('pengembalian.id_peminjaman')
            ->sum('detail_peminjaman.jumlah');

        return [
            'total'    => $tersedia + $dipinjam,
            'tersedia' => $tersedia,
            'dipinjam' => $dipinjam,
        ];
    }
}

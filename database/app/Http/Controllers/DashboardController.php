<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        // Data contoh. Ganti dengan query model asli (Buku, Peminjaman, Pengumuman).
        $totalBuku        = 1250;
        $bukuTersedia     = 980;
        $bukuDipinjam     = 270;
        $bukuDipinjamSaya = 320;

        $bukuList = collect([
            (object) ['id' => 1, 'judul' => 'Judul Buku', 'pengarang' => 'Nama pengarang', 'cover' => null],
            (object) ['id' => 2, 'judul' => 'Judul Buku', 'pengarang' => 'Nama pengarang', 'cover' => null],
            (object) ['id' => 3, 'judul' => 'Judul Buku', 'pengarang' => 'Nama pengarang', 'cover' => null],
        ]);

        $pengumuman = collect([
            (object) ['judul' => 'Jadwal Pengembalian Buku', 'isi' => 'Mohon untuk mengembalikan buku tepat waktu.', 'tanggal' => '2027-09-20'],
            (object) ['judul' => 'Lomba Literasi', 'isi' => 'Dalam rangka dibulan literasi, akan diadakan lomba literasi.', 'tanggal' => '2027-09-18'],
            (object) ['judul' => 'Pengumuman Libur', 'isi' => 'Perpustakaan tutup pada tanggal 25 September 2027.', 'tanggal' => '2027-09-17'],
        ]);

        $riwayat = collect([
            (object) ['pengarang' => 'Andrea Hirata',   'tanggal_pinjam' => '2027-09-15', 'tanggal_kembali' => '2027-09-29'],
            (object) ['pengarang' => 'Jostein Gaarder', 'tanggal_pinjam' => '2027-09-10', 'tanggal_kembali' => '2027-09-24'],
        ]);

        return view('dashboard', compact(
            'totalBuku', 'bukuTersedia', 'bukuDipinjam', 'bukuDipinjamSaya',
            'bukuList', 'pengumuman', 'riwayat'
        ));
    }
}

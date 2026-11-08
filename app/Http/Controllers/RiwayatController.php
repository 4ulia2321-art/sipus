<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RiwayatController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = in_array(Auth::user()->role, ['Admin', 'Pustakawan']);

        return $isAdmin
            ? $this->riwayatAdmin($request)
            : $this->riwayatSiswa($request);
    }

    /**
     * Riwayat untuk admin/pustakawan: seluruh peminjaman.
     */
    private function riwayatAdmin(Request $request)
    {
        $query = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->select(
                'peminjaman.id_peminjaman',
                'detail_peminjaman.id_buku as buku_id',
                'peminjaman.tanggal_pinjam',
                'peminjaman.tanggal_jatuh_tempo',
                'pengembalian.tanggal_kembali',
                'peminjaman.status',
                'siswa.nis',
                'siswa.nama_siswa',
                'siswa.kelas',
                'buku.judul',
                'buku.pengarang',
                'buku.kategori',
                DB::raw("CASE WHEN pengembalian.id_pengembalian IS NOT NULL THEN 'Dikembalikan' ELSE peminjaman.status END as status_efektif")
            );

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('buku.judul', 'like', "%{$q}%")
                    ->orWhere('buku.pengarang', 'like', "%{$q}%")
                    ->orWhere('siswa.nama_siswa', 'like', "%{$q}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'Dikembalikan') {
                $query->whereNotNull('pengembalian.id_pengembalian');
            } else {
                $query->where('peminjaman.status', $status)
                    ->whereNull('pengembalian.id_pengembalian');
            }
        }

        if ($tahun = $request->integer('tahun')) {
            $query->whereYear('peminjaman.tanggal_pinjam', $tahun);
        }

        $riwayat = $query->orderByDesc('peminjaman.id_peminjaman')->paginate(8)->withQueryString();

        $statusList = ['Menunggu', 'Dipinjam', 'Ditolak', 'Dikembalikan'];
        $tahunList = DB::table('peminjaman')
            ->selectRaw('YEAR(tanggal_pinjam) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->all();

        $isAdmin = true;

        return view('admin.riwayat.index', compact('riwayat', 'statusList', 'tahunList', 'isAdmin'));
    }

    /**
     * Riwayat untuk siswa: hanya peminjaman miliknya sendiri.
     */
    private function riwayatSiswa(Request $request)
    {
        $siswaId = DB::table('siswa')->where('id_user', Auth::id())->value('id_siswa');

        $query = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'p.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
            ->where('p.id_siswa', $siswaId)
            ->select(
                'p.id_peminjaman',
                'dp.id_buku as buku_id',
                'p.tanggal_pinjam',
                'p.tanggal_jatuh_tempo',
                'pg.tanggal_kembali',
                'p.status',
                'b.judul',
                'b.pengarang',
                'b.kategori',
                'b.cover',
                DB::raw("CASE
                    WHEN pg.id_pengembalian IS NOT NULL THEN 'dikembalikan'
                    WHEN p.status = 'Dipinjam' AND p.tanggal_jatuh_tempo < CURDATE() THEN 'terlambat'
                    ELSE LOWER(p.status)
                END as status_efektif")
            );

        if ($q = $request->input('q')) {
            $query->where('b.judul', 'like', "%{$q}%");
        }

        if ($status = $request->input('status')) {
            if ($status === 'dikembalikan') {
                $query->whereNotNull('pg.id_pengembalian');
            } elseif ($status === 'terlambat') {
                $query->whereNull('pg.id_pengembalian')
                    ->where('p.status', 'Dipinjam')
                    ->whereDate('p.tanggal_jatuh_tempo', '<', Carbon::today());
            } elseif ($status === 'dipinjam') {
                $query->whereNull('pg.id_pengembalian')
                    ->where('p.status', 'Dipinjam')
                    ->whereDate('p.tanggal_jatuh_tempo', '>=', Carbon::today());
            }
        }

        if ($tahun = $request->integer('tahun')) {
            $query->whereYear('p.tanggal_pinjam', $tahun);
        }

        $riwayat = $query->orderByDesc('p.id_peminjaman')->paginate(8)->withQueryString();

        $riwayat->through(fn ($r) => (object) [
            'cover'           => $r->cover,
            'judul'           => $r->judul,
            'pengarang'       => $r->pengarang,
            'kategori'        => $r->kategori,
            'tanggal_pinjam'  => $r->tanggal_pinjam ? Carbon::parse($r->tanggal_pinjam) : null,
            'jatuh_tempo'     => $r->tanggal_jatuh_tempo ? Carbon::parse($r->tanggal_jatuh_tempo) : null,
            'tanggal_kembali' => $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali) : null,
            'status'          => $r->status_efektif,
            'buku_id'         => $r->buku_id,
        ]);

        $tahunList = DB::table('peminjaman')
            ->where('id_siswa', $siswaId)
            ->selectRaw('YEAR(tanggal_pinjam) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->all();

        return view('riwayat.index', compact('riwayat', 'tahunList'));
    }
}

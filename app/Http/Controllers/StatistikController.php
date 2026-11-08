<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Statistik perpustakaan dipakai oleh Admin dan Pustakawan.
 * Hak akses tetap dibatasi oleh middleware area pada masing-masing route.
 */
class StatistikController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(in_array(Auth::user()->role, ['Admin', 'Pustakawan'], true), 403);

        // Periode grafik tren
        $grafik = in_array((int) $request->input('grafik'), [7, 30], true) ? (int) $request->input('grafik') : 7;

        $selesai = now()->endOfDay();
        $awalGrafik = $selesai->copy()->subDays($grafik - 1)->startOfDay();

        $counts = DB::table('peminjaman')
            ->selectRaw('DATE(tanggal_pinjam) as tgl, COUNT(*) as total')
            ->whereBetween('tanggal_pinjam', [$awalGrafik, $selesai])
            ->groupBy('tgl')
            ->pluck('total', 'tgl');

        $grafikLabel = [];
        $grafikData = [];
        for ($d = $awalGrafik->copy(); $d->lte($selesai); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $grafikLabel[] = $d->translatedFormat('d M');
            $grafikData[] = (int) ($counts[$key] ?? 0);
        }

        // Statistik ringkasan
        $stat = [
            'buku'      => DB::table('buku')->count(),
            'anggota'   => DB::table('siswa')->count(),
            'dipinjam'  => DB::table('peminjaman')->where('status', 'Dipinjam')->count(),
            'menunggu'  => DB::table('peminjaman')->where('status', 'Menunggu')->count(),
            'terlambat' => DB::table('peminjaman as p')
                ->where('p.status', 'Dipinjam')
                ->whereDate('p.tanggal_jatuh_tempo', '<', now()->toDateString())
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('pengembalian as pg')
                        ->whereColumn('pg.id_peminjaman', 'p.id_peminjaman');
                })
                ->count(),
        ];

        // Donut kategori buku terpopuler
        $palette = ['#5b9bec', '#34c38f', '#f6b93b', '#e05a6b', '#8e6bd6', '#28b6c8'];
        $rawKategori = DB::table('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('kategori_buku as k', 'k.id_kategori', '=', 'b.id_kategori')
            ->selectRaw("COALESCE(k.nama_kategori, 'Lainnya') as nama, SUM(dp.jumlah) as total")
            ->groupBy('nama')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $totalKategori = (float) $rawKategori->sum('total');
        $kategori = $rawKategori->values()->map(fn ($r, $i) => [
            'nama'   => $r->nama,
            'total'  => (int) $r->total,
            'persen' => $totalKategori > 0 ? round($r->total / $totalKategori * 100, 1) : 0,
            'warna'  => $palette[$i % count($palette)],
        ])->all();

        // Peminjaman per kelas
        $perKelas = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->selectRaw('s.kelas, COUNT(*) as total')
            ->groupBy('s.kelas')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $kelasLabel = $perKelas->pluck('kelas')->all();
        $kelasData  = $perKelas->pluck('total')->map(fn ($v) => (int) $v)->all();

        // Buku terpopuler
        $populer = DB::table('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('kategori_buku as k', 'k.id_kategori', '=', 'b.id_kategori')
            ->selectRaw("b.judul, COALESCE(k.nama_kategori, '-') as kategori, b.pengarang, SUM(dp.jumlah) as total, b.stok")
            ->groupBy('b.id_buku', 'b.judul', 'k.nama_kategori', 'b.pengarang', 'b.stok')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Ringkasan denda
        $denda = [
            'total' => (float) DB::table('pengembalian')->sum('denda'),
            'belum' => (float) DB::table('pengembalian')->where('status_bayar', 'Belum Lunas')->sum('denda'),
        ];

        return view('admin.statistik.index', compact(
            'grafik', 'grafikLabel', 'grafikData', 'stat', 'kategori',
            'kelasLabel', 'kelasData', 'populer', 'denda'
        ));
    }
}

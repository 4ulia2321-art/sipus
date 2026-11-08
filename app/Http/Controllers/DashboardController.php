<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->role === 'Admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'Pustakawan') {
            return redirect()->route('pustakawan.dashboard');
        }

        return $this->dashboardSiswa($user);
    }

    public function adminDashboard()
    {
        return $this->dashboardAdmin(auth()->user());
    }

    public function pustakawanDashboard()
    {
        return $this->dashboardPustakawan(auth()->user());
    }

    private function dashboardAdmin($user)
    {
        $nama = $user->name ?: ($user->username ?: 'Admin');

        $totalBuku    = DB::table('buku')->count();
        $bukuTersedia = DB::table('buku')->where('stok', '>', 0)->count();
        $bukuDipinjam = DB::table('peminjaman')->where('status', 'Dipinjam')->count();
        $menunggu     = DB::table('peminjaman')->where('status', 'Menunggu')->count();

        $stat = [
            ['ikon' => 'bi-book',            'label' => 'Total Buku',             'nilai' => $totalBuku,    'ket' => 'Judul buku di perpustakaan'],
            ['ikon' => 'bi-bookmark-check',  'label' => 'Buku Tersedia',          'nilai' => $bukuTersedia, 'ket' => 'Siap untuk dipinjam'],
            ['ikon' => 'bi-book-half',       'label' => 'Buku Dipinjam',          'nilai' => $bukuDipinjam, 'ket' => 'Sedang dipinjam anggota'],
            ['ikon' => 'bi-hourglass-split', 'label' => 'Menunggu Persetujuan',   'nilai' => $menunggu,     'ket' => 'Pengajuan yang belum diproses'],
        ];

        $peminjaman = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->select('siswa.nama_siswa', 'buku.judul', 'peminjaman.tanggal_pinjam')
            ->orderByDesc('peminjaman.id_peminjaman')
            ->limit(5)
            ->get()
            ->map(fn ($r) => (object) [
                'foto'    => null,
                'nama'    => $r->nama_siswa,
                'judul'   => $r->judul,
                'tanggal' => Carbon::parse($r->tanggal_pinjam),
            ]);

        $pengembalian = DB::table('pengembalian')
            ->join('peminjaman', 'peminjaman.id_peminjaman', '=', 'pengembalian.id_peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->select('siswa.nama_siswa', 'buku.judul', 'pengembalian.tanggal_kembali')
            ->orderByDesc('pengembalian.id_pengembalian')
            ->limit(5)
            ->get()
            ->map(fn ($r) => (object) [
                'foto'    => null,
                'nama'    => $r->nama_siswa,
                'judul'   => $r->judul,
                'tanggal' => Carbon::parse($r->tanggal_kembali),
            ]);

        $hariIni = Carbon::today()->toDateString();

        $keterlambatan = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->whereRaw("(peminjaman.status = 'Dipinjam' AND peminjaman.tanggal_jatuh_tempo < ?) OR (pengembalian.tanggal_kembali > peminjaman.tanggal_jatuh_tempo)", [$hariIni])
            ->select(
                'siswa.nama_siswa',
                'buku.judul',
                'peminjaman.tanggal_jatuh_tempo',
                'pengembalian.tanggal_kembali'
            )
            ->orderByDesc('peminjaman.tanggal_jatuh_tempo')
            ->get()
            ->map(function ($r) {
                $jatuhTempo = Carbon::parse($r->tanggal_jatuh_tempo);
                $dikembalikan = $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali) : null;

                $lama = $dikembalikan
                    ? $dikembalikan->diffInDays($jatuhTempo)
                    : Carbon::today()->diffInDays($jatuhTempo);

                $lama = max($lama, 1);

                return (object) [
                    'nama'         => $r->nama_siswa,
                    'judul'        => $r->judul,
                    'jatuh_tempo'  => $jatuhTempo,
                    'dikembalikan' => $dikembalikan,
                    'lama'         => $lama > 0 ? "{$lama} hari" : '1 hari',
                    'status'       => 'terlambat',
                ];
            });

        return view('admin.dashboard', [
            'nama'          => $nama,
            'sekarang'      => Carbon::now(),
            'stat'          => $stat,
            'peminjaman'    => $peminjaman,
            'pengembalian'  => $pengembalian,
            'keterlambatan' => $keterlambatan,
        ]);
    }

    private function dashboardPustakawan($user)
    {
        Carbon::setLocale('id');

        $nama = $user->name ?: ($user->username ?: 'Pustakawan');

        $totalBuku    = DB::table('buku')->count();
        $bukuTersedia = DB::table('buku')->where('stok', '>', 0)->count();
        $bukuDipinjam = DB::table('peminjaman')->where('status', 'Dipinjam')->count();
        $menunggu     = DB::table('peminjaman')->where('status', 'Menunggu')->count();
        $totalAnggota = DB::table('siswa')->count();
        $anggotaAktif = DB::table('peminjaman')->where('status', 'Dipinjam')->distinct()->count('id_siswa');

        $terlambat = DB::table('peminjaman')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->whereNull('pengembalian.id_pengembalian')
            ->where('peminjaman.status', 'Dipinjam')
            ->whereDate('peminjaman.tanggal_jatuh_tempo', '<', Carbon::today())
            ->count();

        $stats = [
            ['color' => 'blue',   'icon' => 'bi-book',                 'label' => 'Total Buku',    'value' => $totalBuku,    'delta' => $bukuTersedia, 'delta_text' => 'judul tersedia'],
            ['color' => 'orange', 'icon' => 'bi-journal-arrow-up',     'label' => 'Buku Dipinjam', 'value' => $bukuDipinjam, 'delta' => $menunggu,     'delta_text' => 'menunggu persetujuan'],
            ['color' => 'green',  'icon' => 'bi-people-fill',          'label' => 'Total Anggota', 'value' => $totalAnggota, 'delta' => $anggotaAktif, 'delta_text' => 'sedang meminjam'],
            ['color' => 'red',    'icon' => 'bi-exclamation-triangle', 'label' => 'Terlambat',     'value' => $terlambat,    'delta' => $terlambat,    'delta_text' => 'perlu ditindak'],
        ];

        $menuCepat = [
            ['route' => 'pustakawan.pengajuan',     'color' => 'blue',   'icon' => 'bi-file-earmark-check', 'label' => 'Pengajuan'],
            ['route' => 'pustakawan.persetujuan',   'color' => 'green',  'icon' => 'bi-check2-square',      'label' => 'Persetujuan'],
            ['route' => 'pustakawan.peminjaman',    'color' => 'orange', 'icon' => 'bi-file-earmark-plus',  'label' => 'Transaksi Peminjaman'],
            ['route' => 'pustakawan.pengembalian',  'color' => 'purple', 'icon' => 'bi-clock-history',      'label' => 'Pengembalian'],
            ['route' => 'pustakawan.keterlambatan', 'color' => 'orange', 'icon' => 'bi-exclamation-triangle', 'label' => 'Keterlambatan'],
            ['route' => 'pustakawan.laporan',       'color' => 'blue',   'icon' => 'bi-clipboard-data',     'label' => 'Laporan Peminjaman'],
        ];

        $pengumuman = [
            'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            'isi'     => $terlambat > 0
                ? "Ada {$terlambat} peminjaman yang melewati jatuh tempo dan perlu ditindaklanjuti."
                : 'Tidak ada keterlambatan hari ini. Pertahankan pelayanan perpustakaan!',
        ];

        $grafik = ['labels' => [], 'data' => []];
        for ($i = 6; $i >= 0; $i--) {
            $tanggal = Carbon::today()->subDays($i);
            $grafik['labels'][] = $tanggal->translatedFormat('D');
            $grafik['data'][]   = DB::table('peminjaman')->whereDate('tanggal_pinjam', $tanggal)->count();
        }

        return view('pustakawan.dashboard', [
            'nama'       => $nama,
            'area'       => 'pustakawan',
            'stats'      => $stats,
            'aktivitas'  => $this->aktivitasTerbaru(),
            'kategori'   => $this->kategoriAnggota(),
            'menuCepat'  => $menuCepat,
            'pengumuman' => $pengumuman,
            'grafik'     => $grafik,
        ]);
    }

    private function aktivitasTerbaru()
    {
        $peminjaman = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->orderByDesc('peminjaman.id_peminjaman')
            ->limit(6)
            ->get(['peminjaman.tanggal_pinjam', 'siswa.nama_siswa', 'buku.judul', 'peminjaman.status'])
            ->map(fn ($r) => [
                'waktu'      => Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y'),
                'nama'       => $r->nama_siswa,
                'aktivitas'  => 'Peminjaman',
                'keterangan' => $r->judul,
                'badge'      => $r->status,
                'urut'       => Carbon::parse($r->tanggal_pinjam)->timestamp,
            ]);

        $pengembalian = DB::table('pengembalian')
            ->join('peminjaman', 'peminjaman.id_peminjaman', '=', 'pengembalian.id_peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->orderByDesc('pengembalian.id_pengembalian')
            ->limit(6)
            ->get(['pengembalian.tanggal_kembali', 'siswa.nama_siswa', 'buku.judul', 'pengembalian.kategori'])
            ->map(fn ($r) => [
                'waktu'      => Carbon::parse($r->tanggal_kembali)->translatedFormat('d M Y'),
                'nama'       => $r->nama_siswa,
                'aktivitas'  => 'Pengembalian',
                'keterangan' => $r->judul,
                'badge'      => $r->kategori,
                'urut'       => Carbon::parse($r->tanggal_kembali)->timestamp,
            ]);

        return $peminjaman->concat($pengembalian)->sortByDesc('urut')->take(8)->values();
    }

    private function kategoriAnggota()
    {
        $warna = ['VII' => '#2563eb', 'VIII' => '#16a34a', 'IX' => '#f59e0b'];

        $grup = [];
        foreach (DB::table('siswa')->pluck('kelas') as $kelas) {
            $tingkat = strtoupper(trim(explode(' ', trim((string) $kelas))[0]));
            if ($tingkat !== '') {
                $grup[$tingkat] = ($grup[$tingkat] ?? 0) + 1;
            }
        }

        $kategori = [];
        foreach ($grup as $tingkat => $jumlah) {
            $kategori[] = [
                'nama'   => 'Kelas ' . $tingkat,
                'jumlah' => $jumlah,
                'color'  => $warna[$tingkat] ?? '#7c3aed',
            ];
        }

        return $kategori;
    }

    private function dashboardSiswa($user)
    {
        $idSiswa = DB::table('siswa')->where('id_user', $user->getKey())->value('id_siswa');
        $nama    = DB::table('siswa')->where('id_siswa', $idSiswa)->value('nama_siswa') ?: $user->username;

        $totalBuku        = DB::table('buku')->count();
        $bukuTersedia     = DB::table('buku')->where('stok', '>', 0)->count();
        $bukuDipinjam     = DB::table('peminjaman')->where('status', 'Dipinjam')->count();
        $bukuDipinjamSaya = $idSiswa
            ? DB::table('peminjaman')->where('id_siswa', $idSiswa)->where('status', 'Dipinjam')->count()
            : 0;

        $bukuList = DB::table('buku')
            ->where('stok', '>', 0)
            ->orderBy('judul')
            ->limit(8)
            ->get(['id_buku as id', 'judul', 'pengarang', 'cover'])
            ->map(fn ($b) => (object) [
                'id'        => $b->id,
                'judul'     => $b->judul,
                'pengarang' => $b->pengarang,
                'cover'     => $b->cover,
            ]);

        $riwayat = collect();

        if ($idSiswa) {
            $riwayat = DB::table('peminjaman')
                ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
                ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
                ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
                ->where('peminjaman.id_siswa', $idSiswa)
                ->orderByDesc('peminjaman.id_peminjaman')
                ->limit(5)
                ->get(['buku.pengarang', 'peminjaman.tanggal_pinjam', 'pengembalian.tanggal_kembali'])
                ->map(fn ($r) => (object) [
                    'pengarang'       => $r->pengarang,
                    'tanggal_pinjam'  => $r->tanggal_pinjam,
                    'tanggal_kembali' => $r->tanggal_kembali,
                ]);
        }

        return view('dashboard', [
            'nama'             => $nama,
            'totalBuku'        => $totalBuku,
            'bukuTersedia'     => $bukuTersedia,
            'bukuDipinjam'     => $bukuDipinjam,
            'bukuDipinjamSaya' => $bukuDipinjamSaya,
            'bukuList'         => $bukuList,
            'pengumuman'       => collect(),
            'riwayat'          => $riwayat,
        ]);
    }
}

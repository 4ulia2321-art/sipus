<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
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
                'p.pengajuan_kembali_at',
                'p.perpanjangan_diminta_at',
                'p.perpanjangan_status',
                'pg.tanggal_kembali',
                'p.status',
                'b.judul',
                'b.pengarang',
                'b.kategori',
                'b.cover'
            );

        if ($q = $request->input('q')) {
            $query->where('b.judul', 'like', "%{$q}%");
        }

        if ($status = $request->input('status')) {
            if ($status === 'dikembalikan') {
                $query->whereNotNull('pg.id_pengembalian');
            } elseif ($status === 'terlambat') {
                $query->whereNull('pg.id_pengembalian')
                    ->whereNull('p.pengajuan_kembali_at')
                    ->where('p.status', 'Dipinjam')
                    ->whereDate('p.tanggal_jatuh_tempo', '<', Carbon::today());
            } elseif ($status === 'dipinjam') {
                $query->whereNull('pg.id_pengembalian')
                    ->whereNull('p.pengajuan_kembali_at')
                    ->where('p.status', 'Dipinjam')
                    ->whereDate('p.tanggal_jatuh_tempo', '>=', Carbon::today());
            } elseif ($status === 'menunggu-pengembalian') {
                $query->whereNull('pg.id_pengembalian')
                    ->whereNotNull('p.pengajuan_kembali_at');
            } elseif ($status === 'menunggu-pembayaran') {
                $query->where('p.status', 'Menunggu Pembayaran');
            }
        }

        if ($tahun = $request->integer('tahun')) {
            $query->whereYear('p.tanggal_pinjam', $tahun);
        }

        $riwayat = $query->orderByDesc('p.id_peminjaman')->paginate(8)->withQueryString();
        $riwayat->through(function ($r) {
            [$statusKey, $statusLabel] = $this->hitungStatus($r);

            return (object) [
                'id_peminjaman'        => $r->id_peminjaman,
                'cover'                => $r->cover,
                'judul'                => $r->judul,
                'pengarang'            => $r->pengarang,
                'kategori'             => $r->kategori,
                'tanggal_pinjam'       => $r->tanggal_pinjam ? Carbon::parse($r->tanggal_pinjam) : null,
                'jatuh_tempo'          => $r->tanggal_jatuh_tempo ? Carbon::parse($r->tanggal_jatuh_tempo) : null,
                'tanggal_kembali'      => $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali) : null,
                'pengajuan_kembali_at' => $r->pengajuan_kembali_at ? Carbon::parse($r->pengajuan_kembali_at) : null,
                'perpanjangan_diminta_at' => $r->perpanjangan_diminta_at ? Carbon::parse($r->perpanjangan_diminta_at) : null,
                'perpanjangan_status'  => $r->perpanjangan_status,
                'status'               => $statusKey,
                'status_label'         => $statusLabel,
                'buku_id'              => $r->buku_id,
            ];
        });

        $tahunList = DB::table('peminjaman')
            ->where('id_siswa', $siswaId)
            ->pluck('tanggal_pinjam')
            ->map(fn ($tanggal) => Carbon::parse($tanggal)->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return view('riwayat.index', compact('riwayat', 'tahunList'));
    }

    /**
     * Detail satu peminjaman milik siswa yang sedang login.
     */
    public function show($id)
    {
        $peminjaman = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
            ->where('p.id_peminjaman', $id)
            ->where('s.id_user', Auth::id())
            ->select(
                'p.*',
                's.nama_siswa',
                's.nis',
                's.kelas',
                'pg.tanggal_kembali',
                'pg.denda',
                'pg.kategori as kategori_pengembalian',
                'pg.status_bayar'
            )
            ->first();

        abort_if(! $peminjaman, 404);

        [$statusKey, $statusLabel] = $this->hitungStatus($peminjaman);

        $peminjaman->status_key  = $statusKey;
        $peminjaman->status_label = $statusLabel;
        $peminjaman->bisa_ajukan = in_array($statusKey, ['dipinjam', 'terlambat'], true);
        $peminjaman->perpanjangan_status = $peminjaman->perpanjangan_status ?: null;
        $peminjaman->perpanjangan_menunggu = $peminjaman->perpanjangan_status === 'Menunggu';
        $peminjaman->bisa_perpanjangan = $statusKey === 'dipinjam'
            && ! $peminjaman->perpanjangan_menunggu
            && empty($peminjaman->pengajuan_kembali_at);

        $peminjaman->tanggal_pinjam       = Carbon::parse($peminjaman->tanggal_pinjam);
        $peminjaman->tanggal_jatuh_tempo  = Carbon::parse($peminjaman->tanggal_jatuh_tempo);
        $peminjaman->tanggal_kembali      = $peminjaman->tanggal_kembali ? Carbon::parse($peminjaman->tanggal_kembali) : null;
        $peminjaman->pengajuan_kembali_at = $peminjaman->pengajuan_kembali_at ? Carbon::parse($peminjaman->pengajuan_kembali_at) : null;
        $peminjaman->perpanjangan_diminta_at = $peminjaman->perpanjangan_diminta_at
            ? Carbon::parse($peminjaman->perpanjangan_diminta_at)
            : null;

        // Estimasi denda berjalan untuk peminjaman yang belum dikembalikan.
        $peminjaman->hari_terlambat = 0;
        if (! $peminjaman->tanggal_kembali && $peminjaman->tanggal_jatuh_tempo->lt(Carbon::today())) {
            $peminjaman->hari_terlambat = (int) $peminjaman->tanggal_jatuh_tempo
                ->copy()
                ->startOfDay()
                ->diffInDays(Carbon::today());
        }
        $peminjaman->estimasi_denda = $peminjaman->hari_terlambat
            * Pengaturan::int('denda_per_hari', 500);

        $buku = DB::table('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->where('dp.id_peminjaman', $peminjaman->id_peminjaman)
            ->select('b.*', 'dp.jumlah')
            ->orderBy('b.judul')
            ->get();

        return view('riwayat.show', compact('peminjaman', 'buku'));
    }

    /**
     * Simpan pengajuan pengembalian siswa.
     *
     * Status peminjaman tetap "Dipinjam" agar stok dan statistik tidak
     * berubah sebelum pustakawan mencatat pengembalian. Pengajuan ditandai
     * lewat kolom pengajuan_kembali_at.
     */
    public function ajukanPengembalian($id)
    {
        $peminjaman = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->where('p.id_peminjaman', $id)
            ->where('s.id_user', Auth::id())
            ->select('p.id_peminjaman')
            ->first();

        abort_if(! $peminjaman, 404);

        $hasil = DB::transaction(function () use ($id) {
            $fresh = DB::table('peminjaman')->where('id_peminjaman', $id)->lockForUpdate()->first();

            if (! $fresh) {
                return 'tidak_ditemukan';
            }

            if (DB::table('pengembalian')->where('id_peminjaman', $id)->exists()) {
                return 'sudah_dikembalikan';
            }

            if (! empty($fresh->pengajuan_kembali_at)) {
                return 'sudah_diajukan';
            }

            if ($fresh->status !== 'Dipinjam') {
                return 'status_tidak_sesuai';
            }

            $perubahan = ['pengajuan_kembali_at' => now()];

            // Pengajuan pengembalian membatalkan permintaan perpanjangan
            // yang masih menunggu agar tidak ada dua pengajuan aktif.
            if ($fresh->perpanjangan_status === 'Menunggu') {
                $perubahan['perpanjangan_status'] = null;
                $perubahan['perpanjangan_diminta_at'] = null;
            }

            DB::table('peminjaman')->where('id_peminjaman', $id)->update($perubahan);

            return 'ok';
        });

        return match ($hasil) {
            'ok' => redirect()->route('riwayat')
                ->with('success', 'Pengajuan pengembalian berhasil dikirim. Silakan serahkan buku ke pustakawan untuk diproses.'),
            'sudah_diajukan' => redirect()->route('riwayat')
                ->with('info', 'Pengajuan pengembalian untuk peminjaman ini sudah dikirim dan sedang menunggu diproses pustakawan.'),
            'sudah_dikembalikan' => redirect()->route('riwayat')
                ->with('info', 'Buku untuk peminjaman ini sudah tercatat dikembalikan.'),
            'status_tidak_sesuai' => redirect()->route('riwayat')
                ->with('error', 'Peminjaman ini belum dapat diajukan pengembaliannya.'),
            default => redirect()->route('riwayat')
                ->with('error', 'Peminjaman tidak ditemukan.'),
        };
    }

    /**
     * Simpan permintaan perpanjangan dari siswa.
     *
     * Tanggal jatuh tempo baru ditentukan Pustakawan saat pengajuan
     * diverifikasi. Status peminjaman tetap "Dipinjam" selama menunggu.
     */
    public function ajukanPerpanjangan($id)
    {
        $peminjaman = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->where('p.id_peminjaman', $id)
            ->where('s.id_user', Auth::id())
            ->select('p.id_peminjaman')
            ->first();

        abort_if(! $peminjaman, 404);

        $hasil = DB::transaction(function () use ($id) {
            $fresh = DB::table('peminjaman')->where('id_peminjaman', $id)->lockForUpdate()->first();

            if (! $fresh) {
                return 'tidak_ditemukan';
            }

            if (DB::table('pengembalian')->where('id_peminjaman', $id)->exists()) {
                return 'sudah_dikembalikan';
            }

            if (! empty($fresh->pengajuan_kembali_at)) {
                return 'pengembalian_diajukan';
            }

            if ($fresh->status !== 'Dipinjam') {
                return 'status_tidak_sesuai';
            }

            if ($fresh->perpanjangan_status === 'Menunggu') {
                return 'sudah_diajukan';
            }

            if (Carbon::parse($fresh->tanggal_jatuh_tempo)->lt(Carbon::today())) {
                return 'terlambat';
            }

            DB::table('peminjaman')->where('id_peminjaman', $id)->update([
                'perpanjangan_diminta_at' => now(),
                'perpanjangan_status'     => 'Menunggu',
            ]);

            return 'ok';
        });

        return match ($hasil) {
            'ok' => redirect()->route('riwayat.show', $id)
                ->with('success', 'Pengajuan perpanjangan berhasil dikirim dan menunggu verifikasi pustakawan.'),
            'sudah_diajukan' => redirect()->route('riwayat.show', $id)
                ->with('info', 'Pengajuan perpanjangan untuk peminjaman ini sudah dikirim dan sedang menunggu verifikasi pustakawan.'),
            'sudah_dikembalikan' => redirect()->route('riwayat.show', $id)
                ->with('info', 'Buku untuk peminjaman ini sudah tercatat dikembalikan.'),
            'pengembalian_diajukan' => redirect()->route('riwayat.show', $id)
                ->with('info', 'Pengajuan pengembalian sudah dikirim, sehingga perpanjangan tidak dapat diajukan.'),
            'terlambat' => redirect()->route('riwayat.show', $id)
                ->with('error', 'Peminjaman ini sudah melewati jatuh tempo sehingga tidak dapat diperpanjang.'),
            'status_tidak_sesuai' => redirect()->route('riwayat.show', $id)
                ->with('error', 'Peminjaman ini belum dapat diperpanjang.'),
            default => redirect()->route('riwayat')
                ->with('error', 'Peminjaman tidak ditemukan.'),
        };
    }

    /**
     * Tentukan status tampilan peminjaman siswa.
     *
     * @return array{0: string, 1: string} [key untuk CSS, label untuk teks]
     */
    private function hitungStatus(object $peminjaman): array
    {
        $status = (string) ($peminjaman->status ?? '');

        if ($status === 'Menunggu Pembayaran') {
            return ['menunggu-pembayaran', 'Menunggu Pembayaran'];
        }

        if (! empty($peminjaman->tanggal_kembali) || $status === 'Dikembalikan') {
            return ['dikembalikan', 'Dikembalikan'];
        }

        if (! empty($peminjaman->pengajuan_kembali_at)) {
            return ['menunggu-pengembalian', 'Menunggu Pengembalian'];
        }

        if ($status === 'Dipinjam'
            && ! empty($peminjaman->tanggal_jatuh_tempo)
            && Carbon::parse($peminjaman->tanggal_jatuh_tempo)->lt(Carbon::today())) {
            return ['terlambat', 'Terlambat'];
        }

        return match ($status) {
            'Dipinjam'     => ['dipinjam', 'Dipinjam'],
            'Dikembalikan' => ['dikembalikan', 'Dikembalikan'],
            'Menunggu'     => ['menunggu', 'Menunggu'],
            'Ditolak'      => ['ditolak', 'Ditolak'],
            default        => [strtolower($status), $status ?: 'Tidak Diketahui'],
        };
    }
}

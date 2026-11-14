<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
use App\Support\StokBuku;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Area Pustakawan — dipisahkan dari AdminController agar perubahan kode
 * Pustakawan tidak memengaruhi area Admin (dan sebaliknya).
 *
 * Semua rute di grup /pustakawan memakai controller ini dan mengembalikan
 * view di folder resources/views/pustakawan/.
 */
class PustakawanController extends Controller
{
    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'Pustakawan', 403);
    }

    // =========================================================
    // Peminjaman
    // =========================================================

    public function peminjaman(Request $request)
    {
        $this->ensureAdmin();

        $statusInput = strtolower((string) $request->input('status'));
        $khususTerlambat = $statusInput === 'terlambat' && $request->input('tab') !== 'riwayat';
        $today = Carbon::today();

        // Menu Transaksi Peminjaman memiliki dua kelompok data: transaksi yang
        // masih berjalan dan riwayat transaksi yang sudah dikembalikan.
        $tab = $request->input('tab') === 'riwayat' ? 'riwayat' : 'aktif';

        // Tautan lama (?status=dikembalikan tanpa tab) tetap diarahkan ke riwayat.
        if ($request->input('tab') === null && $statusInput === 'dikembalikan') {
            $tab = 'riwayat';
        }

        $query = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->select(
                'peminjaman.id_peminjaman',
                'siswa.nama_siswa',
                'siswa.kelas',
                'peminjaman.tanggal_pinjam',
                'peminjaman.tanggal_jatuh_tempo',
                'peminjaman.status',
                'peminjaman.pengajuan_kembali_at',
                'peminjaman.perpanjangan_diminta_at',
                'peminjaman.perpanjangan_status',
                'pengembalian.tanggal_kembali',
                'pengembalian.denda',
                'pengembalian.kategori as kategori_pengembalian'
            );

        // Satu baris mewakili satu transaksi. Judul buku digabung agar total
        // tabel sama dengan jumlah transaksi, bukan jumlah baris detail buku.
        $concatJudul = $this->concatJudulSql();

        $query->selectSub(function ($q) use ($concatJudul) {
            $q->from('detail_peminjaman as dp')
                ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                ->whereColumn('dp.id_peminjaman', 'peminjaman.id_peminjaman')
                ->selectRaw($concatJudul);
        }, 'judul')
        ->selectSub(function ($q) {
            $q->from('detail_peminjaman as dp')
                ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                ->whereColumn('dp.id_peminjaman', 'peminjaman.id_peminjaman')
                ->selectRaw('MIN(b.pengarang)');
        }, 'pengarang');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('siswa.nama_siswa', 'like', "%{$q}%")
                    ->orWhere('siswa.nis', 'like', "%{$q}%")
                    ->orWhereExists(function ($sub) use ($q) {
                        $sub->select(DB::raw(1))
                            ->from('detail_peminjaman as dp')
                            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                            ->whereColumn('dp.id_peminjaman', 'peminjaman.id_peminjaman')
                            ->where(function ($b) use ($q) {
                                $b->where('b.judul', 'like', "%{$q}%")
                                    ->orWhere('b.pengarang', 'like', "%{$q}%");
                            });
                    });
            });
        }

        if ($khususTerlambat) {
            $query->where('peminjaman.status', 'Dipinjam')
                ->whereNull('pengembalian.id_peminjaman')
                ->whereNull('peminjaman.pengajuan_kembali_at')
                ->whereDate('peminjaman.tanggal_jatuh_tempo', '<', $today);
        } elseif ($tab === 'riwayat') {
            $query->whereNotNull('pengembalian.id_peminjaman');

            match ($statusInput) {
                'terlambat' => $query->where(function ($w) {
                    $w->where('pengembalian.kategori', 'Terlambat')
                        ->orWhere(function ($w) {
                            $w->whereNull('pengembalian.kategori')
                                ->whereColumn('pengembalian.tanggal_kembali', '>', 'peminjaman.tanggal_jatuh_tempo');
                        });
                }),
                'tepat-waktu' => $query->where(function ($w) {
                    $w->where('pengembalian.kategori', 'Tepat Waktu')
                        ->orWhere(function ($w) {
                            $w->whereNull('pengembalian.kategori')
                                ->whereColumn('pengembalian.tanggal_kembali', '<=', 'peminjaman.tanggal_jatuh_tempo');
                        });
                }),
                default => null,
            };
        } else {
            $query->whereNull('pengembalian.id_peminjaman');

            match ($statusInput) {
                'dipinjam'     => $query->where('peminjaman.status', 'Dipinjam')->whereNull('peminjaman.pengajuan_kembali_at')->whereDate('peminjaman.tanggal_jatuh_tempo', '>=', $today),
                'menunggu-pengembalian' => $query->whereNotNull('peminjaman.pengajuan_kembali_at'),
                'menunggu'     => $query->where('peminjaman.status', 'Menunggu'),
                'ditolak'      => $query->where('peminjaman.status', 'Ditolak'),
                default        => null,
            };
        }

        // Riwayat difilter memakai tanggal kembali; transaksi aktif memakai tanggal pinjam.
        $kolomTanggal = (! $khususTerlambat && $tab === 'riwayat')
            ? 'pengembalian.tanggal_kembali'
            : 'peminjaman.tanggal_pinjam';

        match ($request->input('tanggal')) {
            'hari-ini'  => $query->whereDate($kolomTanggal, $today),
            '7-hari'    => $query->whereDate($kolomTanggal, '>=', $today->copy()->subDays(6)),
            '30-hari'   => $query->whereDate($kolomTanggal, '>=', $today->copy()->subDays(29)),
            'bulan-ini' => $query->whereMonth($kolomTanggal, $today->month)->whereYear($kolomTanggal, $today->year),
            default     => null,
        };

        $peminjaman = $query->orderByDesc('peminjaman.id_peminjaman')->paginate(10)->withQueryString();

        $peminjaman->through(function ($r) {
            $tanggalKembali = $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali) : null;
            $kategori = trim((string) $r->kategori_pengembalian);
            $terlambat = $tanggalKembali !== null && ($kategori !== ''
                ? strcasecmp($kategori, 'Terlambat') === 0
                : $tanggalKembali->greaterThan(Carbon::parse($r->tanggal_jatuh_tempo)));

            if ($r->status === 'Menunggu Pembayaran') {
                $status = 'menunggu-pembayaran';
            } elseif ($tanggalKembali || $r->status === 'Dikembalikan') {
                $status = 'dikembalikan';
            } elseif ($r->pengajuan_kembali_at) {
                $status = 'menunggu-pengembalian';
            } elseif ($r->status === 'Dipinjam' && Carbon::parse($r->tanggal_jatuh_tempo)->lt(Carbon::today())) {
                $status = 'terlambat';
            } else {
                $status = strtolower($r->status);
            }

            return (object) [
                'id'                     => $r->id_peminjaman,
                'foto'                   => null,
                'nama'                   => $r->nama_siswa,
                'kelas'                  => $r->kelas,
                'judul'                  => $r->judul,
                'pengarang'              => $r->pengarang,
                'tanggal_pinjam'         => Carbon::parse($r->tanggal_pinjam),
                'jatuh_tempo'            => Carbon::parse($r->tanggal_jatuh_tempo),
                'tanggal_kembali'        => $tanggalKembali,
                'terlambat'              => $terlambat,
                'denda'                  => (float) ($r->denda ?? 0),
                'kategori_pengembalian'  => $kategori !== '' ? $kategori : null,
                'status'                 => $status,
                'perpanjangan_status'    => $r->perpanjangan_status,
                'perpanjangan_diminta_at'=> $r->perpanjangan_diminta_at ? Carbon::parse($r->perpanjangan_diminta_at) : null,
            ];
        });

        $totalAktif = DB::table('peminjaman')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->whereNull('pengembalian.id_peminjaman')
            ->count();

        $totalRiwayat = DB::table('pengembalian')->count();

        $judulHalaman = $khususTerlambat ? 'Keterlambatan' : 'Peminjaman Buku';
        $deskripsiHalaman = $khususTerlambat
            ? 'Daftar peminjaman yang melewati jatuh tempo dan perlu ditindaklanjuti.'
            : 'Kelola transaksi peminjaman aktif dan riwayat pengembalian.';

        return view('pustakawan.peminjaman.index', compact(
            'peminjaman',
            'judulHalaman',
            'deskripsiHalaman',
            'khususTerlambat',
            'tab',
            'totalAktif',
            'totalRiwayat'
        ));
    }

    /**
     * Halaman khusus keterlambatan: menampilkan peminjaman yang sudah lewat jatuh tempo.
     */
    public function keterlambatan(Request $request)
    {
        $this->ensureAdmin();

        $request->merge(['status' => 'terlambat']);

        return $this->peminjaman($request);
    }

    public function create()
    {
        $this->ensureAdmin();

        $anggota  = DB::table('siswa')->orderBy('nama_siswa')->get();
        $bukuList = DB::table('buku')->where('stok', '>', 0)->orderBy('judul')->get();

        return view('pustakawan.peminjaman.create', compact('anggota', 'bukuList'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'id_siswa'            => ['required', 'integer', 'exists:siswa,id_siswa'],
            'id_buku'             => ['required', 'integer', 'exists:buku,id_buku'],
            'tanggal_pinjam'      => ['required', 'date'],
            'tanggal_jatuh_tempo' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
        ]);

        $buku = DB::table('buku')->where('id_buku', $data['id_buku'])->first();

        if (! $buku || $buku->stok <= 0) {
            return back()->withInput()->with('error', 'Stok buku tidak mencukupi untuk dipinjamkan.');
        }

        DB::transaction(function () use ($data) {
            $idPeminjaman = DB::table('peminjaman')->insertGetId([
                'id_siswa'            => $data['id_siswa'],
                'tanggal_pinjam'      => $data['tanggal_pinjam'],
                'tanggal_jatuh_tempo' => $data['tanggal_jatuh_tempo'],
                'status'              => 'Dipinjam',
            ]);

            DB::table('detail_peminjaman')->insert([
                'id_peminjaman' => $idPeminjaman,
                'id_buku'       => $data['id_buku'],
                'jumlah'        => 1,
            ]);

            DB::table('buku')->where('id_buku', $data['id_buku'])->decrement('stok');
        });

        return redirect(route('pustakawan.peminjaman'))->with('success', 'Peminjaman baru berhasil dicatat.');
    }

    public function show($id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->where('peminjaman.id_peminjaman', $id)
            ->select(
                'peminjaman.*',
                'siswa.nis',
                'siswa.nama_siswa',
                'siswa.kelas',
                'buku.no_seri_buku',
                'buku.judul',
                'buku.pengarang',
                'pengembalian.tanggal_kembali',
                'pengembalian.denda'
            )
            ->first();

        abort_if(! $peminjaman, 404);

        if ($peminjaman->tanggal_kembali || $peminjaman->status === 'Dikembalikan') {
            $peminjaman->status_efektif = 'Dikembalikan';
        } elseif ($peminjaman->pengajuan_kembali_at) {
            $peminjaman->status_efektif = 'Menunggu Pengembalian';
        } elseif ($peminjaman->status === 'Dipinjam' && Carbon::parse($peminjaman->tanggal_jatuh_tempo)->lt(Carbon::today())) {
            $peminjaman->status_efektif = 'Terlambat';
        } else {
            $peminjaman->status_efektif = $peminjaman->status;
        }

        return view('pustakawan.peminjaman.show', compact('peminjaman'));
    }

    public function edit(Request $request, $id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        // Simpan asal halaman agar setelah menyimpan tidak berpindah menu.
        $asal = $request->query('asal') === 'keterlambatan' ? 'keterlambatan' : null;

        return view('pustakawan.peminjaman.edit', compact('peminjaman', 'asal'));
    }

    public function update(Request $request, $id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        $data = $request->validate([
            'tanggal_pinjam'      => ['required', 'date'],
            'tanggal_jatuh_tempo' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'status'              => ['required', 'in:Menunggu,Dipinjam,Ditolak'],
            'asal'                => ['nullable', 'in:keterlambatan'],
        ]);

        $details = DB::table('detail_peminjaman')->where('id_peminjaman', $id)->get();
        $menjadiDipinjam = $peminjaman->status !== 'Dipinjam' && $data['status'] === 'Dipinjam';
        $berhentiDipinjam = $peminjaman->status === 'Dipinjam' && $data['status'] !== 'Dipinjam';

        if ($menjadiDipinjam) {
            foreach ($details as $detail) {
                $stok = (int) DB::table('buku')->where('id_buku', $detail->id_buku)->value('stok');

                if ($stok < (int) $detail->jumlah) {
                    return back()->withInput()->with('error', 'Stok buku tidak mencukupi untuk mengubah status menjadi Dipinjam.');
                }
            }
        }

        // Pengembalian yang sudah tercatat sudah menambah stok, jadi jangan
        // menambahkannya lagi ketika status diubah lewat form edit.
        $sudahDikembalikan = DB::table('pengembalian')->where('id_peminjaman', $id)->exists();

        DB::transaction(function () use ($id, $data, $details, $menjadiDipinjam, $berhentiDipinjam, $sudahDikembalikan) {
            if ($menjadiDipinjam) {
                foreach ($details as $detail) {
                    DB::table('buku')->where('id_buku', $detail->id_buku)->decrement('stok', $detail->jumlah);
                }
            } elseif ($berhentiDipinjam && ! $sudahDikembalikan) {
                foreach ($details as $detail) {
                    DB::table('buku')->where('id_buku', $detail->id_buku)->increment('stok', $detail->jumlah);
                }
            }

            DB::table('peminjaman')->where('id_peminjaman', $id)->update([
                'tanggal_pinjam'      => $data['tanggal_pinjam'],
                'tanggal_jatuh_tempo' => $data['tanggal_jatuh_tempo'],
                'status'              => $data['status'],
            ]);
        });

        $redirect = ($data['asal'] ?? null) === 'keterlambatan'
            ? route('pustakawan.keterlambatan')
            : route('pustakawan.peminjaman');

        return redirect($redirect)->with('success', 'Data peminjaman berhasil diperbarui.');
    }

    public function perpanjang($id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        if ($peminjaman->status !== 'Dipinjam') {
            return back()->with('info', 'Peminjaman ini tidak dapat diperpanjang.');
        }

        if (DB::table('pengembalian')->where('id_peminjaman', $id)->exists()) {
            return back()->with('info', 'Peminjaman ini sudah dicatat sebagai dikembalikan.');
        }

        if (! empty($peminjaman->pengajuan_kembali_at)) {
            return back()->with('info', 'Siswa sudah mengajukan pengembalian, sehingga peminjaman tidak dapat diperpanjang.');
        }

        if ($peminjaman->perpanjangan_status === 'Menunggu') {
            return back()->with('info', 'Pengajuan perpanjangan siswa sudah menunggu verifikasi. Gunakan tombol Setujui atau Tolak.');
        }

        $lama = Pengaturan::int('lama_pinjam_hari', 7);

        DB::table('peminjaman')->where('id_peminjaman', $id)->update([
            'tanggal_jatuh_tempo' => Carbon::parse($peminjaman->tanggal_jatuh_tempo)->addDays($lama)->toDateString(),
        ]);

        return back()->with('success', "Masa peminjaman diperpanjang {$lama} hari.");
    }

    /**
     * Setujui pengajuan perpanjangan siswa dan tambah jatuh tempo.
     */
    public function setujuiPerpanjangan($id)
    {
        $this->ensureAdmin();

        $hasil = DB::transaction(function () use ($id) {
            $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->lockForUpdate()->first();

            if (! $peminjaman) {
                return 'tidak_ditemukan';
            }

            if (DB::table('pengembalian')->where('id_peminjaman', $id)->exists()) {
                return 'sudah_dikembalikan';
            }

            if ($peminjaman->status !== 'Dipinjam') {
                return 'status_tidak_sesuai';
            }

            if ($peminjaman->perpanjangan_status !== 'Menunggu') {
                return 'tidak_ada_pengajuan';
            }

            $lama = Pengaturan::int('lama_pinjam_hari', 7);

            DB::table('peminjaman')->where('id_peminjaman', $id)->update([
                'tanggal_jatuh_tempo' => Carbon::parse($peminjaman->tanggal_jatuh_tempo)->addDays($lama)->toDateString(),
                'perpanjangan_status' => 'Disetujui',
            ]);

            return 'ok';
        });

        return match ($hasil) {
            'ok' => back()->with('success', 'Pengajuan perpanjangan disetujui. Jatuh tempo diperpanjang ' . Pengaturan::int('lama_pinjam_hari', 7) . ' hari.'),
            'sudah_dikembalikan' => back()->with('info', 'Peminjaman ini sudah dicatat sebagai dikembalikan.'),
            'status_tidak_sesuai' => back()->with('error', 'Peminjaman ini belum dipinjam sehingga tidak dapat diperpanjang.'),
            'tidak_ada_pengajuan' => back()->with('info', 'Tidak ada pengajuan perpanjangan yang menunggu verifikasi.'),
            default => back()->with('error', 'Peminjaman tidak ditemukan.'),
        };
    }

    /**
     * Tolak pengajuan perpanjangan siswa tanpa mengubah jatuh tempo.
     */
    public function tolakPerpanjangan($id)
    {
        $this->ensureAdmin();

        $hasil = DB::transaction(function () use ($id) {
            $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->lockForUpdate()->first();

            if (! $peminjaman) {
                return 'tidak_ditemukan';
            }

            if (DB::table('pengembalian')->where('id_peminjaman', $id)->exists()) {
                return 'sudah_dikembalikan';
            }

            if ($peminjaman->perpanjangan_status !== 'Menunggu') {
                return 'tidak_ada_pengajuan';
            }

            DB::table('peminjaman')->where('id_peminjaman', $id)->update([
                'perpanjangan_status' => 'Ditolak',
            ]);

            return 'ok';
        });

        return match ($hasil) {
            'ok' => back()->with('success', 'Pengajuan perpanjangan ditolak.'),
            'sudah_dikembalikan' => back()->with('info', 'Peminjaman ini sudah dicatat sebagai dikembalikan.'),
            'tidak_ada_pengajuan' => back()->with('info', 'Tidak ada pengajuan perpanjangan yang menunggu verifikasi.'),
            default => back()->with('error', 'Peminjaman tidak ditemukan.'),
        };
    }

    /**
     * Catat pengembalian langsung dari daftar/detail peminjaman.
     *
     * Tombol satu-klik ini memakai tanggal hari ini dan menghitung denda
     * otomatis, sehingga Pustakawan tidak perlu mengubah status lewat form edit.
     */
    public function kembalikan($id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        if (DB::table('pengembalian')->where('id_peminjaman', $id)->exists()) {
            return back()->with('info', 'Peminjaman ini sudah dicatat sebagai dikembalikan.');
        }

        if ($peminjaman->status !== 'Dipinjam') {
            return back()->with('error', 'Peminjaman ini belum dipinjam sehingga tidak dapat dikembalikan.');
        }

        $kembali = Carbon::today();
        $batas   = Carbon::parse($peminjaman->tanggal_jatuh_tempo)->startOfDay();
        $telat   = $kembali->greaterThan($batas)
            ? (int) $batas->diffInDays($kembali->copy()->startOfDay())
            : 0;
        $denda   = $telat * Pengaturan::int('denda_per_hari', 500);
        $statusBayar = $denda > 0 ? 'Belum Lunas' : 'Lunas';
        $statusSelesai = $denda > 0 ? 'Menunggu Pembayaran' : 'Dikembalikan';

        DB::transaction(function () use ($peminjaman, $kembali, $telat, $denda, $statusBayar, $statusSelesai) {
            DB::table('pengembalian')->insert([
                'id_peminjaman'   => $peminjaman->id_peminjaman,
                'tanggal_kembali' => $kembali->toDateString(),
                'denda'           => $denda,
                'kategori'        => $telat > 0 ? 'Terlambat' : 'Tepat Waktu',
                'status_bayar'    => $statusBayar,
            ]);

            $details = DB::table('detail_peminjaman')
                ->where('id_peminjaman', $peminjaman->id_peminjaman)
                ->get();

            foreach ($details as $detail) {
                DB::table('buku')->where('id_buku', $detail->id_buku)->increment('stok', $detail->jumlah);
            }

            DB::table('peminjaman')
                ->where('id_peminjaman', $peminjaman->id_peminjaman)
                ->update(['status' => $statusSelesai]);

            if ($peminjaman->perpanjangan_status === 'Menunggu') {
                DB::table('peminjaman')
                    ->where('id_peminjaman', $peminjaman->id_peminjaman)
                    ->update([
                        'perpanjangan_status'     => null,
                        'perpanjangan_diminta_at' => null,
                    ]);
            }
        });

        $pesan = $telat > 0
            ? 'Pengembalian dicatat. Terlambat ' . $telat . ' hari dengan denda Rp ' . number_format($denda, 0, ',', '.') . '. Status: Menunggu Pembayaran.'
            : 'Pengembalian dicatat tepat waktu tanpa denda.';

        return back()->with('success', $pesan);
    }

    // =========================================================
    // Pengembalian
    // =========================================================

    public function pengembalian(Request $request)
    {
        $this->ensureAdmin();

        // Menu pengembalian memiliki dua kelompok data: antrean peminjaman
        // aktif dan riwayat pengembalian. Tab menjaga halaman tetap fokus
        // pada satu tabel tanpa menghilangkan filter yang sedang dipakai.
        $tab = $request->input('tab') === 'riwayat' ? 'riwayat' : 'aktif';

        $concatJudul = $this->concatJudulSql();

        $query = DB::table('pengembalian as p')
            ->join('peminjaman as pm', 'pm.id_peminjaman', '=', 'p.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'pm.id_siswa')
            ->select(
                'p.id_pengembalian as id',
                'p.tanggal_kembali',
                'p.denda',
                'p.kategori',
                'pm.tanggal_pinjam',
                'pm.tanggal_jatuh_tempo as batas_kembali',
                's.id_siswa',
                's.nis',
                's.nama_siswa',
                's.kelas'
            )
            // Satu baris mewakili satu transaksi pengembalian. Detail buku
            // digabung agar total riwayat tidak terlipat ganda.
            ->selectSub(function ($q) use ($concatJudul) {
                $q->from('detail_peminjaman as dp')
                    ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                    ->whereColumn('dp.id_peminjaman', 'pm.id_peminjaman')
                    ->selectRaw($concatJudul);
            }, 'judul')
            ->selectSub(function ($q) {
                $q->from('detail_peminjaman as dp')
                    ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                    ->whereColumn('dp.id_peminjaman', 'pm.id_peminjaman')
                    ->selectRaw('MIN(b.pengarang)');
            }, 'pengarang')
            ->selectSub(function ($q) {
                $q->from('detail_peminjaman as dp')
                    ->whereColumn('dp.id_peminjaman', 'pm.id_peminjaman')
                    ->selectRaw('MIN(dp.id_buku)');
            }, 'buku_id');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('s.nama_siswa', 'like', "%{$q}%")
                    ->orWhere('s.nis', 'like', "%{$q}%")
                    ->orWhereExists(function ($sub) use ($q) {
                        $sub->select(DB::raw(1))
                            ->from('detail_peminjaman as dp')
                            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                            ->whereColumn('dp.id_peminjaman', 'pm.id_peminjaman')
                            ->where(function ($b) use ($q) {
                                $b->where('b.judul', 'like', "%{$q}%")
                                    ->orWhere('b.pengarang', 'like', "%{$q}%");
                            });
                    });
            });
        }

        match ($request->input('status')) {
            'terlambat'   => $query->where('p.kategori', 'Terlambat'),
            'tepat_waktu' => $query->where('p.kategori', 'Tepat Waktu'),
            default       => null,
        };

        if ($anggota = $request->input('anggota')) {
            $query->where('pm.id_siswa', $anggota);
        }

        if ($tanggal = $request->input('tanggal')) {
            $bagian = array_map('trim', explode('-', $tanggal, 2));
            if (count($bagian) === 2) {
                try {
                    $query->whereBetween('p.tanggal_kembali', [
                        Carbon::parse($bagian[0])->startOfDay(),
                        Carbon::parse($bagian[1])->endOfDay(),
                    ]);
                } catch (\Throwable $e) {
                    // rentang tanggal tidak valid, abaikan filter
                }
            }
        }

        $pengembalian = $query->orderByDesc('p.id_pengembalian')->paginate(10)->withQueryString();

        $pengembalian->through(function ($r) {
            $kembali = Carbon::parse($r->tanggal_kembali);
            $batas   = Carbon::parse($r->batas_kembali);

            // Status keterlambatan mengikuti data tersimpan (read-only),
            // dengan fallback perbandingan tanggal untuk data lama tanpa kategori.
            $terlambat = $r->kategori !== null && trim($r->kategori) !== ''
                ? strcasecmp(trim($r->kategori), 'Terlambat') === 0
                : $kembali->greaterThan($batas);

            return (object) [
                'id'              => $r->id,
                'tanggal_kembali' => $kembali,
                'tanggal_pinjam'  => Carbon::parse($r->tanggal_pinjam),
                'batas_kembali'   => $batas,
                'terlambat'       => $terlambat,
                'kategori'        => $r->kategori,
                'denda'           => (float) $r->denda,
                'anggota'         => (object) [
                    'id'         => $r->id_siswa,
                    'nama'       => $r->nama_siswa,
                    'kelas'      => $r->kelas,
                    'avatar_url' => null,
                ],
                'buku'            => (object) [
                    'id'      => $r->buku_id,
                    'judul'   => $r->judul,
                    'penulis' => $r->pengarang,
                ],
            ];
        });

        $anggota = DB::table('siswa')
            ->orderBy('nama_siswa')
            ->get(['id_siswa as id', 'nama_siswa as nama']);

        // Antrean peminjaman aktif agar Pustakawan dapat mencatat pengembalian
        // langsung dengan satu tombol tanpa membuka form manual.
        $antrean = DB::table('peminjaman as pm')
            ->join('siswa as s', 's.id_siswa', '=', 'pm.id_siswa')
            ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'pm.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('pengembalian as p', 'p.id_peminjaman', '=', 'pm.id_peminjaman')
            ->whereNull('p.id_peminjaman')
            ->where('pm.status', 'Dipinjam')
            ->select(
                'pm.id_peminjaman',
                'pm.tanggal_pinjam',
                'pm.tanggal_jatuh_tempo',
                'pm.pengajuan_kembali_at',
                's.nama_siswa',
                's.kelas',
                'b.judul',
                'b.pengarang'
            )
            ->orderByRaw('CASE WHEN pm.pengajuan_kembali_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pm.tanggal_jatuh_tempo')
            ->orderByDesc('pm.id_peminjaman')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                $r->terlambat = Carbon::parse($r->tanggal_jatuh_tempo)->lt(Carbon::today());
                $r->menunggu_pengembalian = (bool) $r->pengajuan_kembali_at;

                return $r;
            });

        $totalAktif = DB::table('peminjaman as pm')
            ->leftJoin('pengembalian as p', 'p.id_peminjaman', '=', 'pm.id_peminjaman')
            ->whereNull('p.id_peminjaman')
            ->where('pm.status', 'Dipinjam')
            ->count();

        return view('pustakawan.pengembalian.index', compact('pengembalian', 'anggota', 'antrean', 'totalAktif', 'tab'));
    }

    public function pengembalianCreate()
    {
        $this->ensureAdmin();

        $peminjamanAktif = DB::table('peminjaman as pm')
            ->join('siswa as s', 's.id_siswa', '=', 'pm.id_siswa')
            ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'pm.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('pengembalian as p', 'p.id_peminjaman', '=', 'pm.id_peminjaman')
            ->whereNull('p.id_peminjaman')
            ->where('pm.status', 'Dipinjam')
            ->select(
                'pm.id_peminjaman',
                's.nama_siswa',
                's.nis',
                'b.judul',
                'pm.tanggal_pinjam',
                'pm.tanggal_jatuh_tempo',
                'pm.pengajuan_kembali_at'
            )
            ->orderByDesc('pm.pengajuan_kembali_at')
            ->orderBy('s.nama_siswa')
            ->get();

        return view('pustakawan.pengembalian.create', compact('peminjamanAktif'));
    }

    public function pengembalianStore(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'id_peminjaman'   => ['required', 'integer', 'exists:peminjaman,id_peminjaman'],
            'tanggal_kembali' => ['required', 'date'],
            'denda'           => ['nullable', 'numeric', 'min:0'],
            'kategori'        => ['nullable', 'string', 'max:50'],
        ]);

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $data['id_peminjaman'])->first();
        abort_if(! $peminjaman, 404);

        if (DB::table('pengembalian')->where('id_peminjaman', $peminjaman->id_peminjaman)->exists()) {
            return back()->withInput()->with('info', 'Peminjaman ini sudah memiliki data pengembalian.');
        }

        if (! in_array($peminjaman->status, ['Dipinjam', 'Terlambat'], true)) {
            return back()->withInput()->with('error', 'Peminjaman ini belum dipinjam sehingga tidak dapat dikembalikan.');
        }

        $kembali = Carbon::parse($data['tanggal_kembali']);
        $batas   = Carbon::parse($peminjaman->tanggal_jatuh_tempo);
        $telat   = $kembali->greaterThan($batas) ? (int) $batas->startOfDay()->diffInDays($kembali->copy()->startOfDay()) : 0;
        $denda   = $data['denda'] ?? $telat * Pengaturan::int('denda_per_hari', 500);
        $statusBayar = $denda > 0 ? 'Belum Lunas' : 'Lunas';
        $statusSelesai = $denda > 0 ? 'Menunggu Pembayaran' : 'Dikembalikan';

        DB::transaction(function () use ($data, $peminjaman, $kembali, $telat, $denda, $statusBayar, $statusSelesai) {
            DB::table('pengembalian')->insert([
                'id_peminjaman'   => $peminjaman->id_peminjaman,
                'tanggal_kembali' => $kembali->toDateString(),
                'denda'           => $denda,
                'kategori'        => $data['kategori'] ?? ($telat > 0 ? 'Terlambat' : 'Tepat Waktu'),
                'status_bayar'    => $statusBayar,
            ]);

            $details = DB::table('detail_peminjaman')->where('id_peminjaman', $peminjaman->id_peminjaman)->get();
            foreach ($details as $d) {
                DB::table('buku')->where('id_buku', $d->id_buku)->increment('stok', $d->jumlah);
            }

            DB::table('peminjaman')
                ->where('id_peminjaman', $peminjaman->id_peminjaman)
                ->update(['status' => $statusSelesai]);

            if ($peminjaman->perpanjangan_status === 'Menunggu') {
                DB::table('peminjaman')
                    ->where('id_peminjaman', $peminjaman->id_peminjaman)
                    ->update([
                        'perpanjangan_status'     => null,
                        'perpanjangan_diminta_at' => null,
                    ]);
            }
        });

        return redirect(route('pustakawan.pengembalian'))->with('success', $denda > 0
            ? 'Pengembalian berhasil dicatat. Denda Rp ' . number_format($denda, 0, ',', '.') . ' menunggu pelunasan.'
            : 'Pengembalian berhasil dicatat.');
    }

    public function pengembalianShow($id)
    {
        $this->ensureAdmin();

        $r = DB::table('pengembalian as p')
            ->join('peminjaman as pm', 'pm.id_peminjaman', '=', 'p.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'pm.id_siswa')
            ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'pm.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->where('p.id_pengembalian', $id)
            ->select(
                'p.id_pengembalian',
                'p.tanggal_kembali',
                'p.denda',
                'p.kategori',
                'p.status_bayar',
                'pm.id_peminjaman',
                'pm.tanggal_pinjam',
                'pm.tanggal_jatuh_tempo',
                'pm.status',
                's.nis',
                's.nama_siswa',
                's.kelas',
                'b.no_seri_buku',
                'b.judul',
                'b.pengarang'
            )
            ->first();

        abort_if(! $r, 404);

        $kembali = Carbon::parse($r->tanggal_kembali);
        $batas   = Carbon::parse($r->tanggal_jatuh_tempo);

        $r->tanggal_kembali     = $kembali;
        $r->tanggal_pinjam      = Carbon::parse($r->tanggal_pinjam);
        $r->tanggal_jatuh_tempo = $batas;

        // Status keterlambatan mengikuti kategori tersimpan agar hasil edit
        // langsung terlihat. Data lama tanpa kategori memakai perbandingan tanggal.
        $r->terlambat = $r->kategori !== null && trim($r->kategori) !== ''
            ? strcasecmp(trim($r->kategori), 'Terlambat') === 0
            : $kembali->greaterThan($batas);

        $r->lama_terlambat = $r->terlambat
            ? (int) $batas->copy()->startOfDay()->diffInDays($kembali->copy()->startOfDay())
            : 0;

        return view('pustakawan.pengembalian.show', ['pengembalian' => $r]);
    }

    // =========================================================
    // Anggota
    // =========================================================

    public function anggota(Request $request)
    {
        $this->ensureAdmin();

        $query = DB::table('siswa');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('nama_siswa', 'like', "%{$q}%")
                    ->orWhere('nis', 'like', "%{$q}%")
                    ->orWhere('kelas', 'like', "%{$q}%");
            });
        }

        // Skema saat ini hanya menyimpan anggota siswa (belum ada guru/staf TU).
        if (in_array($request->input('jenis'), ['Guru', 'Staf TU'], true)) {
            $query->whereRaw('1 = 0');
        }

        if ($kelas = $request->input('kelas')) {
            $query->where('kelas', $kelas);
        }

        // Belum ada kolom status; semua anggota siswa dianggap aktif.
        if ($request->input('status') === 'Nonaktif') {
            $query->whereRaw('1 = 0');
        }

        $paginator = $query->orderBy('nama_siswa')->paginate(10)->withQueryString();
        $start = ($paginator->currentPage() - 1) * $paginator->perPage();

        $dipinjam = array_flip(
            DB::table('peminjaman')->where('status', 'Dipinjam')->pluck('id_siswa')->all()
        );

        $anggota = $paginator->through(function ($r, $key) use ($start, $dipinjam) {
            return [
                'id'         => $r->id_siswa,
                'no'         => $start + $key + 1,
                'foto'       => null,
                'warna'      => $this->warnaAvatar($r->nama_siswa),
                'nama'       => $r->nama_siswa,
                'nomor'      => $r->nis,
                'nis'        => $r->nis,
                'kelas'      => $r->kelas,
                'jenis'      => 'Siswa',
                'status'     => 'Aktif',
                'bisa_hapus' => ! isset($dipinjam[$r->id_siswa]),
            ];
        });

        $daftarKelas  = DB::table('siswa')->distinct()->orderBy('kelas')->pluck('kelas')->all();
        $daftarStatus = ['Aktif', 'Nonaktif'];

        return view('pustakawan.anggota.index', compact('anggota', 'daftarKelas', 'daftarStatus'));
    }

    private function warnaAvatar(string $nama): string
    {
        $palette = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#db2777'];

        return $palette[crc32($nama) % count($palette)];
    }

    public function anggotaCreate()
    {
        $this->ensureAdmin();

        return view('pustakawan.anggota.create', ['daftarKelas' => $this->daftarKelas()]);
    }

    private function daftarKelas(): array
    {
        return DB::table('kelas')->orderBy('nama_kelas')->pluck('nama_kelas')
            ->merge(DB::table('siswa')->whereNotNull('kelas')->distinct()->pluck('kelas'))
            ->filter(fn ($k) => trim((string) $k) !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function anggotaStore(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'username'   => ['required', 'string', 'max:100', 'unique:t_user,username'],
            'password'   => ['required', 'string', 'min:6'],
            'nis'        => ['required', 'string', 'max:20', 'unique:siswa,nis'],
            'nama_siswa'    => ['required', 'string', 'max:100'],
            'kelas'         => ['required', 'string', 'max:30'],
            'jenis_kelamin' => ['nullable', 'in:Laki-laki,Perempuan'],
            'tempat_lahir'  => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat'        => ['nullable', 'string'],
            'no_telp'       => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($data) {
            $idUser = DB::table('t_user')->insertGetId([
                'username'   => $data['username'],
                'password'   => Hash::make($data['password']),
                'role'       => 'Siswa',
                'created_at' => now(),
            ]);

            DB::table('siswa')->insert([
                'id_user'       => $idUser,
                'nis'           => $data['nis'],
                'nama_siswa'    => $data['nama_siswa'],
                'kelas'         => $data['kelas'],
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'tempat_lahir'  => $data['tempat_lahir'] ?? null,
                'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                'alamat'        => $data['alamat'] ?? null,
                'no_telp'       => $data['no_telp'] ?? null,
            ]);
        });

        return redirect(route('pustakawan.anggota'))->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function anggotaShow($id)
    {
        $this->ensureAdmin();

        $anggota = DB::table('siswa as s')
            ->leftJoin('t_user as u', 'u.id_user', '=', 's.id_user')
            ->where('s.id_siswa', $id)
            ->select('s.*', 'u.username')
            ->selectRaw('(SELECT COUNT(*) FROM peminjaman p WHERE p.id_siswa = s.id_siswa) as total_pinjam')
            ->selectRaw("(SELECT COUNT(*) FROM peminjaman p WHERE p.id_siswa = s.id_siswa AND p.status = 'Dipinjam') as sedang_pinjam")
            ->first();

        abort_if(! $anggota, 404);

        $riwayat = DB::table('peminjaman as pm')
            ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'pm.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('pengembalian as p', 'p.id_peminjaman', '=', 'pm.id_peminjaman')
            ->where('pm.id_siswa', $id)
            ->select(
                'b.judul',
                'pm.tanggal_pinjam',
                'pm.tanggal_jatuh_tempo',
                'pm.status',
                'p.tanggal_kembali'
            )
            ->orderByDesc('pm.id_peminjaman')
            ->limit(10)
            ->get();

        return view('pustakawan.anggota.show', compact('anggota', 'riwayat'));
    }

    public function anggotaEdit($id)
    {
        $this->ensureAdmin();

        $anggota = DB::table('siswa as s')
            ->leftJoin('t_user as u', 'u.id_user', '=', 's.id_user')
            ->where('s.id_siswa', $id)
            ->select('s.*', 'u.username')
            ->first();

        abort_if(! $anggota, 404);

        return view('pustakawan.anggota.edit', [
            'anggota'     => $anggota,
            'daftarKelas' => $this->daftarKelas(),
        ]);
    }

    public function anggotaUpdate(Request $request, $id)
    {
        $this->ensureAdmin();

        $anggota = DB::table('siswa')->where('id_siswa', $id)->first();
        abort_if(! $anggota, 404);

        $data = $request->validate([
            'username'   => ['required', 'string', 'max:100', Rule::unique('t_user', 'username')->ignore($anggota->id_user, 'id_user')],
            'password'   => ['nullable', 'string', 'min:6'],
            'nis'        => ['required', 'string', 'max:20', Rule::unique('siswa', 'nis')->ignore($id, 'id_siswa')],
            'nama_siswa'    => ['required', 'string', 'max:100'],
            'kelas'         => ['required', 'string', 'max:30'],
            'jenis_kelamin' => ['nullable', 'in:Laki-laki,Perempuan'],
            'tempat_lahir'  => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat'        => ['nullable', 'string'],
            'no_telp'       => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($data, $anggota) {
            DB::table('t_user')->where('id_user', $anggota->id_user)->update([
                'username' => $data['username'],
            ]);

            if (! empty($data['password'])) {
                DB::table('t_user')->where('id_user', $anggota->id_user)->update([
                    'password' => Hash::make($data['password']),
                ]);
            }

            DB::table('siswa')->where('id_siswa', $anggota->id_siswa)->update([
                'nis'           => $data['nis'],
                'nama_siswa'    => $data['nama_siswa'],
                'kelas'         => $data['kelas'],
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'tempat_lahir'  => $data['tempat_lahir'] ?? null,
                'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                'alamat'        => $data['alamat'] ?? null,
                'no_telp'       => $data['no_telp'] ?? null,
            ]);
        });

        return redirect(route('pustakawan.anggota'))->with('success', 'Data anggota berhasil diperbarui.');
    }

    public function anggotaDestroy($id)
    {
        $this->ensureAdmin();

        $anggota = DB::table('siswa')->where('id_siswa', $id)->first();
        abort_if(! $anggota, 404);

        if (DB::table('peminjaman')->where('id_siswa', $anggota->id_siswa)->where('status', 'Dipinjam')->exists()) {
            return redirect(route('pustakawan.anggota'))
                ->with('error', 'Anggota tidak dapat dihapus karena masih memiliki peminjaman aktif.');
        }

        DB::transaction(function () use ($anggota) {
            $pmIds = DB::table('peminjaman')->where('id_siswa', $anggota->id_siswa)->pluck('id_peminjaman');
            DB::table('pengembalian')->whereIn('id_peminjaman', $pmIds)->delete();
            DB::table('detail_peminjaman')->whereIn('id_peminjaman', $pmIds)->delete();
            DB::table('peminjaman')->whereIn('id_peminjaman', $pmIds)->delete();
            DB::table('siswa')->where('id_siswa', $anggota->id_siswa)->delete();
            DB::table('t_user')->where('id_user', $anggota->id_user)->delete();
        });

        return redirect(route('pustakawan.anggota'))->with('success', 'Anggota berhasil dihapus.');
    }

    // =========================================================
    // Data Kelas
    // =========================================================

    public function kelas(Request $request)
    {
        $this->ensureAdmin();

        $query = DB::table('kelas as k')
            ->select('k.id_kelas as id', 'k.nama_kelas', 'k.tingkat', 'k.wali_kelas', 'k.created_at')
            ->selectSub(function ($q) {
                $q->from('siswa')->whereColumn('siswa.kelas', 'k.nama_kelas')->selectRaw('COUNT(*)');
            }, 'jumlah_siswa');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('k.nama_kelas', 'like', "%{$q}%")
                    ->orWhere('k.wali_kelas', 'like', "%{$q}%");
            });
        }

        if ($tingkat = $request->input('tingkat')) {
            $query->where('k.tingkat', $tingkat);
        }

        $rows = $query->orderBy('k.tingkat')->orderBy('k.nama_kelas')->paginate(10)->withQueryString();

        $rows->through(fn ($r) => (object) [
            'id'           => $r->id,
            'nama_kelas'   => $r->nama_kelas,
            'tingkat'      => $r->tingkat,
            'wali_kelas'   => $r->wali_kelas,
            'jumlah_siswa' => (int) $r->jumlah_siswa,
            'bisa_hapus'   => (int) $r->jumlah_siswa === 0,
        ]);

        $stat = [
            'total'    => DB::table('kelas')->count(),
            'tingkat7' => DB::table('kelas')->where('tingkat', 'VII')->count(),
            'tingkat8' => DB::table('kelas')->where('tingkat', 'VIII')->count(),
            'tingkat9' => DB::table('kelas')->where('tingkat', 'IX')->count(),
        ];

        $daftarTingkat = DB::table('kelas')->whereNotNull('tingkat')->distinct()->orderBy('tingkat')->pluck('tingkat');

        return view('pustakawan.kelas.index', compact('rows', 'stat', 'daftarTingkat'));
    }

    public function kelasCreate()
    {
        $this->ensureAdmin();

        return view('pustakawan.kelas.create');
    }

    public function kelasStore(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'nama_kelas' => ['required', 'string', 'max:50'],
            'tingkat'    => ['nullable', 'string', 'max:20'],
            'wali_kelas' => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('kelas')->insert([
            'nama_kelas' => $data['nama_kelas'],
            'tingkat'    => $data['tingkat'] ?? null,
            'wali_kelas' => $data['wali_kelas'] ?? null,
            'created_at' => now(),
        ]);

        return redirect()->route('pustakawan.kelas')->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function kelasEdit($id)
    {
        $this->ensureAdmin();

        $kelas = DB::table('kelas')->where('id_kelas', $id)->first();
        abort_if(! $kelas, 404);

        return view('pustakawan.kelas.edit', compact('kelas'));
    }

    public function kelasUpdate(Request $request, $id)
    {
        $this->ensureAdmin();

        $kelas = DB::table('kelas')->where('id_kelas', $id)->first();
        abort_if(! $kelas, 404);

        $data = $request->validate([
            'nama_kelas' => ['required', 'string', 'max:50'],
            'tingkat'    => ['nullable', 'string', 'max:20'],
            'wali_kelas' => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('kelas')->where('id_kelas', $id)->update([
            'nama_kelas' => $data['nama_kelas'],
            'tingkat'    => $data['tingkat'] ?? null,
            'wali_kelas' => $data['wali_kelas'] ?? null,
        ]);

        // Sinkronkan nama kelas pada data siswa bila berubah
        if ($kelas->nama_kelas !== $data['nama_kelas']) {
            DB::table('siswa')->where('kelas', $kelas->nama_kelas)->update(['kelas' => $data['nama_kelas']]);
        }

        return redirect()->route('pustakawan.kelas')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function kelasDestroy($id)
    {
        $this->ensureAdmin();

        $kelas = DB::table('kelas')->where('id_kelas', $id)->first();
        abort_if(! $kelas, 404);

        $jumlah = DB::table('siswa')->where('kelas', $kelas->nama_kelas)->count();
        if ($jumlah > 0) {
            return back()->with('error', 'Kelas tidak dapat dihapus karena masih memiliki ' . $jumlah . ' anggota.');
        }

        DB::table('kelas')->where('id_kelas', $id)->delete();

        return redirect()->route('pustakawan.kelas')->with('success', 'Kelas berhasil dihapus.');
    }

    // =========================================================
    // Data Buku
    // =========================================================

    public function buku(Request $request)
    {
        $this->ensureAdmin();

        $query = DB::table('buku')
            ->leftJoin('kategori_buku', 'kategori_buku.id_kategori', '=', 'buku.id_kategori')
            ->select('buku.*', 'kategori_buku.nama_kategori');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('buku.judul', 'like', "%{$q}%")
                    ->orWhere('buku.pengarang', 'like', "%{$q}%")
                    ->orWhere('buku.penerbit', 'like', "%{$q}%")
                    ->orWhere('buku.no_seri_buku', 'like', "%{$q}%");
            });
        }

        if ($kategori = $request->input('kategori')) {
            $query->where('kategori_buku.nama_kategori', $kategori);
        }

        if ($tahun = $request->input('tahun')) {
            $query->where('buku.tahun_terbit', $tahun);
        }

        switch ($request->input('status')) {
            case 'Tersedia':
                $query->where('buku.stok', '>', 2);
                break;
            case 'Hampir Habis':
                $query->whereBetween('buku.stok', [1, 2]);
                break;
            case 'Habis':
                $query->where('buku.stok', '<=', 0);
                break;
        }

        $paginator = $query->orderBy('buku.judul')->paginate(10)->withQueryString();
        $start = ($paginator->currentPage() - 1) * $paginator->perPage();

        $dipakai = array_flip(
            DB::table('detail_peminjaman')->distinct()->pluck('id_buku')->all()
        );

        $buku = $paginator->through(function ($r, $key) use ($start, $dipakai) {
            $stok = (int) $r->stok;

            return [
                'id'         => $r->id_buku,
                'no'         => $start + $key + 1,
                'cover'      => $this->coverGradient($r->nama_kategori),
                'seri'       => $r->no_seri_buku ?: '-',
                'judul'      => $r->judul,
                'pengarang'  => $r->pengarang ?: '-',
                'penerbit'   => $r->penerbit ?: '-',
                'kategori'   => $r->nama_kategori ?? ($r->kategori ?: '-'),
                'tahun'      => $r->tahun_terbit ?: '-',
                'rak'        => $r->lokasi_rak ?: '-',
                'stok'       => $stok,
                'status'     => $stok <= 0 ? 'Habis' : ($stok <= 2 ? 'Hampir Habis' : 'Tersedia'),
                'bisa_hapus' => ! isset($dipakai[$r->id_buku]),
            ];
        });

        $stok = StokBuku::ringkasan();

        $stats = [
            ['color' => 'blue',   'icon' => 'bi-book',              'label' => 'Total Buku', 'value' => $stok['total'],    'mini' => 'bi-collection', 'sub' => 'total salinan'],
            ['color' => 'green',  'icon' => 'bi-check-circle',      'label' => 'Tersedia',   'value' => $stok['tersedia'], 'mini' => 'bi-bookmark-check', 'sub' => 'salinan siap dipinjam'],
            ['color' => 'violet', 'icon' => 'bi-journal-arrow-up',  'label' => 'Dipinjam',   'value' => $stok['dipinjam'], 'mini' => 'bi-arrow-left-right', 'sub' => 'salinan sedang beredar'],
            ['color' => 'red',    'icon' => 'bi-exclamation-circle', 'label' => 'Stok Habis', 'value' => DB::table('buku')->where('stok', '<=', 0)->count(), 'mini' => 'bi-box-seam', 'sub' => 'judul perlu restock'],
        ];

        $daftarKategori = DB::table('kategori_buku')->orderBy('nama_kategori')->pluck('nama_kategori')->all();
        $daftarTahun    = DB::table('buku')->whereNotNull('tahun_terbit')->distinct()->orderByDesc('tahun_terbit')->pluck('tahun_terbit')->all();
        $daftarStatus   = ['Tersedia', 'Hampir Habis', 'Habis'];

        return view('pustakawan.buku.index', compact('buku', 'stats', 'daftarKategori', 'daftarTahun', 'daftarStatus'));
    }

    public function bukuCreate()
    {
        $this->ensureAdmin();

        $kategori = DB::table('kategori_buku')->orderBy('nama_kategori')->get();

        return view('pustakawan.buku.create', compact('kategori'));
    }

    public function bukuStore(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'no_seri_buku' => ['required', 'string', 'max:30', 'unique:buku,no_seri_buku'],
            'judul'        => ['required', 'string', 'max:200'],
            'pengarang'    => ['nullable', 'string', 'max:100'],
            'penerbit'     => ['nullable', 'string', 'max:100'],
            'id_kategori'  => ['required', 'integer', 'exists:kategori_buku,id_kategori'],
            'tahun_terbit' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'lokasi_rak'   => ['nullable', 'string', 'max:50'],
            'stok'         => ['required', 'integer', 'min:0'],
        ]);

        DB::table('buku')->insert([
            'id_kategori'  => $data['id_kategori'],
            'no_seri_buku' => $data['no_seri_buku'],
            'judul'        => $data['judul'],
            'pengarang'    => $data['pengarang'] ?? null,
            'penerbit'     => $data['penerbit'] ?? null,
            'tahun_terbit' => $data['tahun_terbit'] ?? null,
            'kategori'     => DB::table('kategori_buku')->where('id_kategori', $data['id_kategori'])->value('nama_kategori'),
            'lokasi_rak'   => $data['lokasi_rak'] ?? null,
            'stok'         => $data['stok'],
        ]);

        return redirect()->route('pustakawan.buku')->with('success', 'Buku berhasil ditambahkan.');
    }

    public function bukuEdit($id)
    {
        $this->ensureAdmin();

        $buku = DB::table('buku')->where('id_buku', $id)->first();
        abort_if(! $buku, 404);

        $kategori = DB::table('kategori_buku')->orderBy('nama_kategori')->get();

        return view('pustakawan.buku.edit', compact('buku', 'kategori'));
    }

    public function bukuUpdate(Request $request, $id)
    {
        $this->ensureAdmin();

        $buku = DB::table('buku')->where('id_buku', $id)->first();
        abort_if(! $buku, 404);

        $data = $request->validate([
            'no_seri_buku' => ['required', 'string', 'max:30', Rule::unique('buku', 'no_seri_buku')->ignore($id, 'id_buku')],
            'judul'        => ['required', 'string', 'max:200'],
            'pengarang'    => ['nullable', 'string', 'max:100'],
            'penerbit'     => ['nullable', 'string', 'max:100'],
            'id_kategori'  => ['required', 'integer', 'exists:kategori_buku,id_kategori'],
            'tahun_terbit' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'lokasi_rak'   => ['nullable', 'string', 'max:50'],
            'stok'         => ['required', 'integer', 'min:0'],
        ]);

        DB::table('buku')->where('id_buku', $id)->update([
            'id_kategori'  => $data['id_kategori'],
            'no_seri_buku' => $data['no_seri_buku'],
            'judul'        => $data['judul'],
            'pengarang'    => $data['pengarang'] ?? null,
            'penerbit'     => $data['penerbit'] ?? null,
            'tahun_terbit' => $data['tahun_terbit'] ?? null,
            'kategori'     => DB::table('kategori_buku')->where('id_kategori', $data['id_kategori'])->value('nama_kategori'),
            'lokasi_rak'   => $data['lokasi_rak'] ?? null,
            'stok'         => $data['stok'],
        ]);

        return redirect()->route('pustakawan.buku')->with('success', 'Data buku berhasil diperbarui.');
    }

    public function bukuDestroy($id)
    {
        $this->ensureAdmin();

        $buku = DB::table('buku')->where('id_buku', $id)->first();
        abort_if(! $buku, 404);

        if (DB::table('detail_peminjaman')->where('id_buku', $id)->exists()) {
            return redirect()->route('pustakawan.buku')
                ->with('error', 'Buku tidak dapat dihapus karena memiliki riwayat peminjaman.');
        }

        DB::table('buku')->where('id_buku', $id)->delete();

        return redirect()->route('pustakawan.buku')->with('success', 'Buku berhasil dihapus.');
    }

    private function coverGradient(?string $kategori): array
    {
        $map = [
            'Pelajaran' => ['#2563eb', '#60a5fa'],
            'Cerita'    => ['#7c3aed', '#a78bfa'],
            'Fiksi'     => ['#7c3aed', '#c084fc'],
            'Sastra'    => ['#0ea5e9', '#38bdf8'],
            'Sejarah'   => ['#d97706', '#fbbf24'],
            'IPA'       => ['#16a34a', '#4ade80'],
            'Teknologi' => ['#7c3aed', '#a78bfa'],
            'Bahasa'    => ['#ef4444', '#f87171'],
        ];

        if ($kategori && isset($map[$kategori])) {
            return $map[$kategori];
        }

        $palette = [
            ['#2563eb', '#60a5fa'],
            ['#16a34a', '#4ade80'],
            ['#d97706', '#fbbf24'],
            ['#7c3aed', '#a78bfa'],
            ['#ef4444', '#f87171'],
        ];

        return $palette[$kategori ? (crc32($kategori) % count($palette)) : 0];
    }

    // =========================================================
    // Denda
    // =========================================================

    public function denda(Request $request)
    {
        $this->ensureAdmin();

        $concatJudul = $this->concatJudulSql();

        $query = DB::table('pengembalian as pg')
            ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->select(
                'pg.id_pengembalian as id',
                's.nama_siswa',
                's.nis',
                's.kelas',
                'p.tanggal_pinjam',
                'p.tanggal_jatuh_tempo',
                'pg.tanggal_kembali',
                'pg.denda',
                'pg.kategori',
                'pg.status_bayar'
            )
            ->selectSub(function ($q) use ($concatJudul) {
                $q->from('detail_peminjaman as dp')
                    ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                    ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
                    ->selectRaw($concatJudul);
            }, 'buku');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('s.nama_siswa', 'like', "%{$q}%")
                    ->orWhere('s.nis', 'like', "%{$q}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('pg.status_bayar', $status);
        }

        if ($request->input('hanya_denda') === '1') {
            $query->where('pg.denda', '>', 0);
        }

        $rows = $query->orderByDesc('pg.tanggal_kembali')->orderByDesc('pg.id_pengembalian')->paginate(10)->withQueryString();

        $rows->through(fn ($r) => (object) [
            'id'           => $r->id,
            'nama'         => $r->nama_siswa,
            'nis'          => $r->nis,
            'kelas'        => $r->kelas,
            'buku'         => $r->buku ?: '-',
            'tanggal_kembali' => $r->tanggal_kembali,
            'jatuh_tempo'  => $r->tanggal_jatuh_tempo,
            'denda'        => (float) $r->denda,
            'kategori'     => $r->kategori,
            'status_bayar' => $r->status_bayar ?: 'Belum Lunas',
        ]);

        $stat = [
            'total_denda' => (float) DB::table('pengembalian')->where('denda', '>', 0)->sum('denda'),
            'belum'       => (float) DB::table('pengembalian')->where('status_bayar', 'Belum Lunas')->where('denda', '>', 0)->sum('denda'),
            'lunas'       => (float) DB::table('pengembalian')->where('status_bayar', 'Lunas')->where('denda', '>', 0)->sum('denda'),
            'jumlah_belum'=> DB::table('pengembalian')->where('status_bayar', 'Belum Lunas')->where('denda', '>', 0)->count(),
        ];

        return view('pustakawan.denda.index', compact('rows', 'stat'));
    }

    public function dendaEdit($id)
    {
        $this->ensureAdmin();

        $denda = DB::table('pengembalian as pg')
            ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->where('pg.id_pengembalian', $id)
            ->select(
                'pg.id_pengembalian',
                'pg.denda',
                'pg.kategori',
                'pg.status_bayar',
                'pg.tanggal_kembali',
                's.nama_siswa',
                'p.tanggal_jatuh_tempo'
            )
            ->first();

        abort_if(! $denda, 404);

        return view('pustakawan.denda.edit', compact('denda'));
    }

    public function dendaUpdate(Request $request, $id)
    {
        $this->ensureAdmin();

        $denda = DB::table('pengembalian')->where('id_pengembalian', $id)->first();
        abort_if(! $denda, 404);

        $data = $request->validate([
            'denda'        => ['required', 'numeric', 'min:0'],
            'status_bayar' => ['required', 'in:Belum Lunas,Lunas'],
        ]);

        // Denda nol tidak mungkin ditagih, sehingga langsung dianggap lunas.
        $statusBayar = (float) $data['denda'] <= 0 ? 'Lunas' : $data['status_bayar'];

        DB::transaction(function () use ($id, $denda, $data, $statusBayar) {
            DB::table('pengembalian')->where('id_pengembalian', $id)->update([
                'denda'        => $data['denda'],
                'status_bayar' => $statusBayar,
            ]);

            $this->sinkronStatusPengembalian((int) $denda->id_peminjaman, (float) $data['denda'], $statusBayar);
        });

        return redirect(route('pustakawan.denda'))->with('success', 'Data denda berhasil diperbarui.');
    }

    public function dendaToggle($id)
    {
        $this->ensureAdmin();

        $denda = DB::table('pengembalian')->where('id_pengembalian', $id)->first();
        abort_if(! $denda, 404);

        if ((float) $denda->denda <= 0) {
            return back()->with('info', 'Denda Rp 0 sudah dianggap lunas.');
        }

        $baru = ($denda->status_bayar === 'Lunas') ? 'Belum Lunas' : 'Lunas';

        DB::transaction(function () use ($id, $denda, $baru) {
            DB::table('pengembalian')->where('id_pengembalian', $id)->update(['status_bayar' => $baru]);
            $this->sinkronStatusPengembalian((int) $denda->id_peminjaman, (float) $denda->denda, $baru);
        });

        return back()->with('success', 'Denda ditandai sebagai ' . $baru . '.');
    }

    /**
     * Status peminjaman hanya dianggap selesai bila denda sudah lunas.
     */
    private function sinkronStatusPengembalian(int $idPeminjaman, float $denda, string $statusBayar): void
    {
        $status = ($denda <= 0 || $statusBayar === 'Lunas')
            ? 'Dikembalikan'
            : 'Menunggu Pembayaran';

        DB::table('peminjaman')
            ->where('id_peminjaman', $idPeminjaman)
            ->update(['status' => $status]);
    }

    // =========================================================
    // Laporan
    // =========================================================

    public function laporan(Request $request)
    {
        $this->ensureAdmin();

        [$mulai, $selesai, $periode] = $this->rentangLaporan($request);

        // Tab: laporan Pustakawan hanya mencakup peminjaman & pengembalian.
        $tab = $request->input('tab');
        if (! in_array($tab, ['peminjaman', 'pengembalian'], true)) {
            $tab = 'peminjaman';
        }

        $ringkasTransaksi = null;

        // Rekap denda mengikuti tab dan periode laporan yang sedang dipilih.
        $rekapDenda = $this->rekapDenda($tab, $mulai, $selesai);

        // Statistik
        $stok = StokBuku::ringkasan();

        $stat = [
            'buku'         => $stok['total'],
            'tersedia'     => $stok['tersedia'],
            'dipinjam'     => $stok['dipinjam'],
            'anggota'      => DB::table('siswa')->count(),
            'peminjaman'   => DB::table('peminjaman')->whereBetween('tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])->count(),
            'pengembalian' => DB::table('pengembalian')->whereBetween('tanggal_kembali', [$mulai->toDateString(), $selesai->toDateString()])->count(),
        ];

        // Grafik batang peminjaman (7 / 30 hari)
        $grafik = in_array((int) $request->input('grafik'), [7, 30], true) ? (int) $request->input('grafik') : 7;

        $awalGrafik = $selesai->copy()->subDays($grafik - 1)->startOfDay();
        $counts = DB::table('peminjaman')
            ->selectRaw('DATE(tanggal_pinjam) as tgl, COUNT(*) as total')
            ->whereBetween('tanggal_pinjam', [$awalGrafik->toDateString(), $selesai->toDateString()])
            ->groupBy('tgl')
            ->pluck('total', 'tgl');

        $grafikLabel = [];
        $grafikData = [];
        for ($d = $awalGrafik->copy(); $d->lte($selesai); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $grafikLabel[] = $d->translatedFormat('d M');
            $grafikData[] = (int) ($counts[$key] ?? 0);
        }

        // Donut kategori buku terbanyak dipinjam
        $palette = ['#5b9bec', '#34c38f', '#f6b93b', '#e05a6b', '#8e6bd6', '#28b6c8'];
        $rawKategori = DB::table('detail_peminjaman as dp')
            ->join('peminjaman as p', 'p.id_peminjaman', '=', 'dp.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->leftJoin('kategori_buku as k', 'k.id_kategori', '=', 'b.id_kategori')
            ->whereBetween('p.tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])
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

        // Subquery judul buku per peminjaman
        $concatJudul = $this->concatJudulSql();
        $bukuSub = fn ($q) => $q->from('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
            ->selectRaw($concatJudul);

        // Tabel per tab
        if ($tab === 'pengembalian') {
            $kolom = ['Tanggal Pinjam', 'Tanggal Kembali'];

            $rows = DB::table('pengembalian as pg')
                ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->select('pg.id_pengembalian', 's.nama_siswa', 'p.tanggal_pinjam', 'pg.tanggal_kembali', 'pg.denda', 'pg.status_bayar')
                ->selectSub($bukuSub, 'buku')
                ->whereBetween('pg.tanggal_kembali', [$mulai->toDateString(), $selesai->toDateString()])
                ->orderByDesc('pg.tanggal_kembali')
                ->orderByDesc('pg.id_pengembalian')
                ->paginate(10)
                ->withQueryString();

            $rows->through(function ($r) {
                $belumLunas = (float) ($r->denda ?? 0) > 0
                    && ($r->status_bayar ?: 'Belum Lunas') !== 'Lunas';

                return [
                    'anggota' => $r->nama_siswa,
                    'buku'    => $r->buku ?: '-',
                    'tgl1'    => $r->tanggal_pinjam ? Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y') : '-',
                    'tgl2'    => $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali)->translatedFormat('d M Y') : '-',
                    'status'  => $belumLunas ? 'Menunggu Pembayaran' : 'Dikembalikan',
                    'kelas'   => $belumLunas ? 'badge-warning' : 'badge-success',
                ];
            });
        } elseif ($tab === 'keterlambatan') {
            $kolom = ['Jatuh Tempo', 'Terlambat'];
            $today = now()->startOfDay();

            $rows = DB::table('peminjaman as p')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->select('p.id_peminjaman', 's.nama_siswa', 'p.tanggal_jatuh_tempo')
                ->selectSub($bukuSub, 'buku')
                ->where('p.status', 'Dipinjam')
                ->whereDate('p.tanggal_jatuh_tempo', '<', $today)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('pengembalian as pg')
                        ->whereColumn('pg.id_peminjaman', 'p.id_peminjaman');
                })
                ->orderBy('p.tanggal_jatuh_tempo')
                ->paginate(10)
                ->withQueryString();

            $rows->through(function ($r) use ($today) {
                $hari = (int) Carbon::parse($r->tanggal_jatuh_tempo)->startOfDay()->diffInDays($today);

                return [
                    'anggota' => $r->nama_siswa,
                    'buku'    => $r->buku ?: '-',
                    'tgl1'    => Carbon::parse($r->tanggal_jatuh_tempo)->translatedFormat('d M Y'),
                    'tgl2'    => $hari . ' hari',
                    'status'  => 'Terlambat',
                    'kelas'   => 'badge-danger',
                ];
            });
        } elseif ($tab === 'transaksi') {
            $kolom = ['Tgl Pinjam', 'Tgl Kembali'];

            $rows = DB::table('peminjaman as p')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
                ->select('p.id_peminjaman', 's.nama_siswa', 'p.tanggal_pinjam', 'p.tanggal_jatuh_tempo', 'p.status', 'pg.tanggal_kembali', 'pg.denda', 'pg.status_bayar')
                ->selectSub($bukuSub, 'buku')
                ->whereBetween('p.tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])
                ->orderByDesc('p.tanggal_pinjam')
                ->orderByDesc('p.id_peminjaman')
                ->paginate(10)
                ->withQueryString();

            $rows->through(function ($r) {
                $status = $r->status;
                if ($r->tanggal_kembali) {
                    $status = 'Dikembalikan';
                } elseif ($status === 'Dipinjam' && Carbon::parse($r->tanggal_jatuh_tempo)->startOfDay()->lt(now()->startOfDay())) {
                    $status = 'Terlambat';
                }

                return [
                    'anggota'      => $r->nama_siswa,
                    'buku'         => $r->buku ?: '-',
                    'tgl1'         => $r->tanggal_pinjam ? Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y') : '-',
                    'tgl2'         => $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali)->translatedFormat('d M Y') : '-',
                    'status'       => $status,
                    'kelas'        => $this->statusBadgeClass($status),
                    'denda'        => (float) ($r->denda ?? 0),
                    'status_bayar' => $r->status_bayar ?: 'Belum Lunas',
                ];
            });

            $ringkasTransaksi = [
                'total' => $rows->total(),
                'total_denda' => (float) DB::table('pengembalian as pg')
                    ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
                    ->whereBetween('p.tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])
                    ->sum('pg.denda'),
                'belum' => (float) DB::table('pengembalian as pg')
                    ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
                    ->whereBetween('p.tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])
                    ->where('pg.status_bayar', 'Belum Lunas')
                    ->sum('pg.denda'),
            ];
        } else {
            $kolom = ['Tanggal Pinjam', 'Jatuh Tempo'];

            $rows = DB::table('peminjaman as p')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->select('p.id_peminjaman', 's.nama_siswa', 'p.tanggal_pinjam', 'p.tanggal_jatuh_tempo', 'p.status')
                ->selectSub($bukuSub, 'buku')
                ->whereBetween('p.tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])
                ->orderByDesc('p.tanggal_pinjam')
                ->orderByDesc('p.id_peminjaman')
                ->paginate(10)
                ->withQueryString();

            $rows->through(fn ($r) => [
                'anggota' => $r->nama_siswa,
                'buku'    => $r->buku ?: '-',
                'tgl1'    => $r->tanggal_pinjam ? Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y') : '-',
                'tgl2'    => $r->tanggal_jatuh_tempo ? Carbon::parse($r->tanggal_jatuh_tempo)->translatedFormat('d M Y') : '-',
                'status'  => $r->status,
                'kelas'   => $this->statusBadgeClass($r->status),
            ]);
        }

        return view('pustakawan.laporan.index', compact(
            'tab', 'mulai', 'selesai', 'periode', 'stat', 'grafik', 'grafikLabel', 'grafikData', 'kategori', 'kolom', 'rows', 'ringkasTransaksi', 'rekapDenda'
        ));
    }

    /**
     * Halaman cetak laporan peminjaman / pengembalian.
     * Memuat seluruh baris pada periode terpilih (tanpa pagination).
     */
    public function laporanCetak(Request $request)
    {
        $this->ensureAdmin();

        [$mulai, $selesai, $periode] = $this->rentangLaporan($request);

        $tab = $request->input('tab');
        if (! in_array($tab, ['peminjaman', 'pengembalian'], true)) {
            $tab = 'peminjaman';
        }

        [$kolom, $rows] = $this->dataLaporanCetak($tab, $mulai, $selesai);
        $rekapDenda = $this->rekapDenda($tab, $mulai, $selesai);

        return view('pustakawan.laporan.cetak', compact(
            'tab', 'mulai', 'selesai', 'periode', 'kolom', 'rows', 'rekapDenda'
        ));
    }

    /**
     * Laporan denda keterlambatan sebagai halaman tersendiri.
     */
    public function laporanDenda(Request $request)
    {
        $this->ensureAdmin();

        [$mulai, $selesai, $periode] = $this->rentangLaporan($request);
        $filter = $this->filterLaporanDenda($request);

        $rows = $this->queryLaporanDenda($mulai, $selesai, $filter)
            ->orderByDesc('pg.tanggal_kembali')
            ->orderByDesc('pg.id_pengembalian')
            ->paginate(10)
            ->withQueryString();

        $rows->through(fn ($row, $index) => $this->barisLaporanDenda(
            $row,
            ($rows->firstItem() ?? 0) + $index
        ));

        $rekap = $this->rekapLaporanDenda($mulai, $selesai, $filter);
        $tarif = Pengaturan::int('denda_per_hari', 500);

        $kelasList = DB::table('siswa')
            ->select('kelas')
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        return view('pustakawan.laporan.denda', compact(
            'mulai', 'selesai', 'periode', 'filter', 'rows', 'rekap', 'tarif', 'kelasList'
        ));
    }

    /**
     * Unduh laporan denda sebagai PDF A4.
     */
    public function laporanDendaPdf(Request $request)
    {
        $this->ensureAdmin();

        [$mulai, $selesai, $periode] = $this->rentangLaporan($request);
        $filter = $this->filterLaporanDenda($request);

        $rows = $this->queryLaporanDenda($mulai, $selesai, $filter)
            ->orderBy('pg.tanggal_kembali')
            ->orderBy('pg.id_pengembalian')
            ->get()
            ->values()
            ->map(fn ($row, $index) => $this->barisLaporanDenda($row, $index + 1));

        $rekap = $this->rekapLaporanDenda($mulai, $selesai, $filter);
        $tarif = Pengaturan::int('denda_per_hari', 500);
        $dicetakPada = now()->translatedFormat('d F Y, H:i');
        $petugas = auth()->user()->name ?: (auth()->user()->username ?: 'Pustakawan');

        $pdf = Pdf::loadView('pustakawan.laporan.denda-pdf', compact(
            'mulai', 'selesai', 'periode', 'filter', 'rows', 'rekap', 'tarif', 'dicetakPada', 'petugas'
        ))->setPaper('a4', 'portrait');

        $namaFile = 'laporan-denda-' . $mulai->format('Ymd') . '-' . $selesai->format('Ymd') . '.pdf';

        return $pdf->download($namaFile);
    }

    /**
     * Filter laporan denda yang diizinkan.
     *
     * @return array{q:string,status:string,kelas:string,kategori:string}
     */
    private function filterLaporanDenda(Request $request): array
    {
        $status = (string) $request->input('status', '');
        $kategori = (string) $request->input('kategori', '');

        return [
            'q'        => trim((string) $request->input('q', '')),
            'status'   => in_array($status, ['Lunas', 'Belum Lunas'], true) ? $status : '',
            'kelas'    => trim((string) $request->input('kelas', '')),
            'kategori' => in_array($kategori, ['Terlambat', 'Tepat Waktu'], true) ? $kategori : '',
        ];
    }

    /**
     * Query dasar laporan denda (hanya pengembalian yang benar-benar berdenda).
     */
    private function baseLaporanDenda(Carbon $mulai, Carbon $selesai, array $filter)
    {
        $query = DB::table('pengembalian as pg')
            ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->whereBetween('pg.tanggal_kembali', [$mulai->toDateString(), $selesai->toDateString()])
            ->where('pg.denda', '>', 0);

        if ($filter['q'] !== '') {
            $kata = $filter['q'];

            $query->where(function ($where) use ($kata) {
                $where->where('s.nama_siswa', 'like', "%{$kata}%")
                    ->orWhere('s.nis', 'like', "%{$kata}%")
                    ->orWhere('s.kelas', 'like', "%{$kata}%")
                    ->orWhereExists(function ($sub) use ($kata) {
                        $sub->select(DB::raw(1))
                            ->from('detail_peminjaman as dp')
                            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                            ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
                            ->where('b.judul', 'like', "%{$kata}%");
                    });
            });
        }

        if ($filter['status'] !== '') {
            $query->where('pg.status_bayar', $filter['status']);
        }

        if ($filter['kelas'] !== '') {
            $query->where('s.kelas', $filter['kelas']);
        }

        if ($filter['kategori'] !== '') {
            $query->where('pg.kategori', $filter['kategori']);
        }

        return $query;
    }

    /**
     * Query tabel laporan denda beserta judul buku.
     */
    private function queryLaporanDenda(Carbon $mulai, Carbon $selesai, array $filter)
    {
        $concatJudul = $this->concatJudulSql();

        return $this->baseLaporanDenda($mulai, $selesai, $filter)
            ->select(
                'pg.id_pengembalian as id',
                's.nama_siswa',
                's.nis',
                's.kelas',
                'p.tanggal_pinjam',
                'p.tanggal_jatuh_tempo',
                'pg.tanggal_kembali',
                'pg.denda',
                'pg.kategori',
                'pg.status_bayar'
            )
            ->selectSub(function ($sub) use ($concatJudul) {
                $sub->from('detail_peminjaman as dp')
                    ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                    ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
                    ->selectRaw($concatJudul);
            }, 'buku');
    }

    /**
     * Rekap denda lunas/belum lunas sesuai filter aktif.
     *
     * @return array{jumlah_transaksi:int,jumlah_lunas:int,jumlah_belum:int,total_denda:float,lunas:float,belum:float}
     */
    private function rekapLaporanDenda(Carbon $mulai, Carbon $selesai, array $filter): array
    {
        $ringkas = $this->baseLaporanDenda($mulai, $selesai, $filter)
            ->selectRaw('COUNT(*) as jumlah_transaksi')
            ->selectRaw('COALESCE(SUM(pg.denda), 0) as total_denda')
            ->selectRaw("COALESCE(SUM(CASE WHEN pg.status_bayar = 'Lunas' THEN pg.denda ELSE 0 END), 0) as lunas")
            ->selectRaw("COALESCE(SUM(CASE WHEN pg.status_bayar = 'Lunas' THEN 1 ELSE 0 END), 0) as jumlah_lunas")
            ->first();

        $total = (float) ($ringkas->total_denda ?? 0);
        $lunas = (float) ($ringkas->lunas ?? 0);
        $jumlah = (int) ($ringkas->jumlah_transaksi ?? 0);
        $jumlahLunas = (int) ($ringkas->jumlah_lunas ?? 0);

        return [
            'jumlah_transaksi' => $jumlah,
            'jumlah_lunas'     => $jumlahLunas,
            'jumlah_belum'     => max(0, $jumlah - $jumlahLunas),
            'total_denda'      => $total,
            'lunas'            => $lunas,
            'belum'            => max(0, $total - $lunas),
        ];
    }

    /**
     * Bentuk satu baris laporan denda.
     *
     * @return array<string, mixed>
     */
    private function barisLaporanDenda(object $row, int $nomor = 0): array
    {
        $jatuhTempo = Carbon::parse($row->tanggal_jatuh_tempo)->startOfDay();
        $kembali = Carbon::parse($row->tanggal_kembali)->startOfDay();
        $hari = max(0, (int) $jatuhTempo->diffInDays($kembali, false));
        $statusBayar = $row->status_bayar ?: 'Belum Lunas';

        if ((float) $row->denda <= 0) {
            $statusBayar = 'Lunas';
        }

        return [
            'no'              => $nomor,
            'id'              => (int) $row->id,
            'nama'            => $row->nama_siswa,
            'nis'             => $row->nis,
            'kelas'           => $row->kelas ?: '-',
            'buku'            => $row->buku ?: '-',
            'tanggal_pinjam'  => Carbon::parse($row->tanggal_pinjam)->translatedFormat('d M Y'),
            'jatuh_tempo'     => $jatuhTempo->translatedFormat('d M Y'),
            'tanggal_kembali' => $kembali->translatedFormat('d M Y'),
            'hari'            => $hari,
            'denda'           => (float) $row->denda,
            'kategori'        => $row->kategori ?: ($hari > 0 ? 'Terlambat' : 'Tepat Waktu'),
            'status_bayar'    => $statusBayar,
        ];
    }

    /**
     * Menentukan rentang tanggal laporan berdasarkan pilihan periode.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function rentangLaporan(Request $request): array
    {
        Carbon::setLocale('id');

        $periode = $request->input('periode', 'kustom');
        $hariIni = Carbon::today();

        if ($periode === 'harian') {
            return [$hariIni->copy()->startOfDay(), $hariIni->copy()->endOfDay(), 'harian'];
        }

        if ($periode === 'bulanan') {
            return [$hariIni->copy()->startOfMonth(), $hariIni->copy()->endOfMonth(), 'bulanan'];
        }

        if ($periode === 'tahunan') {
            return [$hariIni->copy()->startOfYear(), $hariIni->copy()->endOfYear(), 'tahunan'];
        }

        $mulai = $request->filled('mulai')
            ? Carbon::parse($request->input('mulai'))->startOfDay()
            : $hariIni->copy()->subDays(29)->startOfDay();

        $selesai = $request->filled('selesai')
            ? Carbon::parse($request->input('selesai'))->endOfDay()
            : $hariIni->copy()->endOfDay();

        if ($mulai->gt($selesai)) {
            $mulai = $selesai->copy()->startOfDay();
        }

        return [$mulai, $selesai, 'kustom'];
    }

    /**
     * Data tabel untuk halaman cetak (tanpa pagination).
     *
     * @return array{0: array<int, string>, 1: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    private function dataLaporanCetak(string $tab, Carbon $mulai, Carbon $selesai): array
    {
        $concatJudul = $this->concatJudulSql();
        $bukuSub = fn ($q) => $q->from('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
            ->selectRaw($concatJudul);

        if ($tab === 'pengembalian') {
            $kolom = ['Tanggal Pinjam', 'Tanggal Kembali'];

            $rows = DB::table('pengembalian as pg')
                ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->select('pg.id_pengembalian', 's.nama_siswa', 'p.tanggal_pinjam', 'pg.tanggal_kembali', 'pg.denda', 'pg.status_bayar')
                ->selectSub($bukuSub, 'buku')
                ->whereBetween('pg.tanggal_kembali', [$mulai->toDateString(), $selesai->toDateString()])
                ->orderBy('pg.tanggal_kembali')
                ->orderBy('pg.id_pengembalian')
                ->get()
                ->map(function ($r, $i) {
                    $belumLunas = (float) ($r->denda ?? 0) > 0
                        && ($r->status_bayar ?: 'Belum Lunas') !== 'Lunas';

                    return [
                        'no'           => $i + 1,
                        'anggota'      => $r->nama_siswa,
                        'buku'         => $r->buku ?: '-',
                        'tgl1'         => Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y'),
                        'tgl2'         => Carbon::parse($r->tanggal_kembali)->translatedFormat('d M Y'),
                        'status'       => $belumLunas ? 'Menunggu Pembayaran' : 'Dikembalikan',
                        'denda'        => (float) ($r->denda ?? 0),
                        'status_bayar' => $r->status_bayar ?: 'Belum Lunas',
                    ];
                });

            return [$kolom, $rows];
        }

        $kolom = ['Tanggal Pinjam', 'Jatuh Tempo'];

        $rows = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
            ->select('p.id_peminjaman', 's.nama_siswa', 'p.tanggal_pinjam', 'p.tanggal_jatuh_tempo', 'p.status', 'pg.tanggal_kembali', 'pg.denda', 'pg.status_bayar')
            ->selectSub($bukuSub, 'buku')
            ->whereBetween('p.tanggal_pinjam', [$mulai->toDateString(), $selesai->toDateString()])
            ->orderBy('p.tanggal_pinjam')
            ->orderBy('p.id_peminjaman')
            ->get()
            ->map(fn ($r, $i) => [
                'no'           => $i + 1,
                'anggota'      => $r->nama_siswa,
                'buku'         => $r->buku ?: '-',
                'tgl1'         => Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y'),
                'tgl2'         => Carbon::parse($r->tanggal_jatuh_tempo)->translatedFormat('d M Y'),
                'status'       => $this->statusLaporan($r),
                'denda'        => (float) ($r->denda ?? 0),
                'status_bayar' => $r->status_bayar ?: 'Belum Lunas',
            ]);

        return [$kolom, $rows];
    }

    /**
     * Rekap denda pada periode laporan.
     *
     * Untuk tab peminjaman, denda dihitung dari transaksi yang tanggal
     * pinjamnya masuk periode. Untuk tab pengembalian, dihitung dari
     * transaksi yang tanggal kembalinya masuk periode.
     *
     * @return array{total_transaksi:int,total_denda:float,lunas:float,belum:float,jumlah_berdenda:int}
     */
    private function rekapDenda(string $tab, Carbon $mulai, Carbon $selesai): array
    {
        $awal = $mulai->toDateString();
        $akhir = $selesai->toDateString();

        // Jumlah transaksi mengikuti tab yang dipilih.
        if ($tab === 'pengembalian') {
            $totalTransaksi = DB::table('pengembalian')
                ->whereBetween('tanggal_kembali', [$awal, $akhir])
                ->count();
        } else {
            $totalTransaksi = DB::table('peminjaman')
                ->whereBetween('tanggal_pinjam', [$awal, $akhir])
                ->count();
        }

        // Nominal denda selalu mengikuti tanggal pengembalian, yaitu saat denda terjadi.
        // Dengan begitu ringkasan Total Denda, Denda Lunas, dan Denda Belum Lunas
        // tetap terisi otomatis pada tab peminjaman, termasuk untuk peminjaman
        // dari periode sebelumnya yang baru dikembalikan pada periode laporan.
        $query = DB::table('pengembalian as pg')
            ->whereBetween('pg.tanggal_kembali', [$awal, $akhir]);

        $ringkas = $query
            ->selectRaw('COALESCE(SUM(pg.denda), 0) as total_denda')
            ->selectRaw("COALESCE(SUM(CASE WHEN pg.status_bayar = 'Lunas' THEN pg.denda ELSE 0 END), 0) as lunas")
            ->selectRaw('COALESCE(SUM(CASE WHEN pg.denda > 0 THEN 1 ELSE 0 END), 0) as jumlah_berdenda')
            ->first();

        $totalDenda = (float) ($ringkas->total_denda ?? 0);
        $lunas = (float) ($ringkas->lunas ?? 0);

        return [
            'total_transaksi' => $totalTransaksi,
            'total_denda'     => $totalDenda,
            'lunas'           => $lunas,
            'belum'           => max(0, $totalDenda - $lunas),
            'jumlah_berdenda' => (int) ($ringkas->jumlah_berdenda ?? 0),
        ];
    }

    /**
     * Status baris laporan peminjaman pada halaman cetak.
     */
    private function statusLaporan(object $row): string
    {
        if (! empty($row->tanggal_kembali)) {
            $belumLunas = (float) ($row->denda ?? 0) > 0
                && ($row->status_bayar ?? 'Belum Lunas') !== 'Lunas';

            return $belumLunas ? 'Menunggu Pembayaran' : 'Dikembalikan';
        }

        if (($row->status ?? null) === 'Dipinjam'
            && ! empty($row->tanggal_jatuh_tempo)
            && Carbon::parse($row->tanggal_jatuh_tempo)->startOfDay()->lt(now()->startOfDay())) {
            return 'Terlambat';
        }

        return $row->status ?: '-';
    }

    private function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Dikembalikan'        => 'badge-success',
            'Dipinjam'            => 'badge-info',
            'Menunggu'            => 'badge-warning',
            'Menunggu Pembayaran' => 'badge-warning',
            'Ditolak'             => 'badge-danger',
            'Terlambat'           => 'badge-danger',
            default               => 'badge-secondary',
        };
    }

    /**
     * SQL penggabung judul buku yang kompatibel dengan MySQL dan SQLite (tes).
     */
    private function concatJudulSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "GROUP_CONCAT(b.judul, ', ')"
            : "GROUP_CONCAT(b.judul SEPARATOR ', ')";
    }
}

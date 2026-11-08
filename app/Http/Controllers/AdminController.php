<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'Admin', 403);
    }

    private function ensureAdminOnly(): void
    {
        abort_unless(Auth::user()->role === 'Admin', 403);
    }

    /**
     * Membangun URL rute sesuai area pengguna yang login, sehingga aksi
     * Pustakawan kembali ke area /pustakawan dan aksi Admin ke area /admin.
     */
    private function areaRoute(string $name, array $params = []): string
    {
        $prefix = (Auth::user()->role ?? null) === 'Pustakawan' ? 'pustakawan' : 'admin';

        return route($prefix . '.' . $name, $params);
    }

    public function peminjaman(Request $request)
    {
        $this->ensureAdmin();

        $query = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->select(
                'peminjaman.id_peminjaman',
                'siswa.nama_siswa',
                'siswa.kelas',
                'buku.judul',
                'buku.pengarang',
                'peminjaman.tanggal_pinjam',
                'peminjaman.tanggal_jatuh_tempo',
                'peminjaman.status',
                'pengembalian.tanggal_kembali'
            );

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('buku.judul', 'like', "%{$q}%")
                    ->orWhere('buku.pengarang', 'like', "%{$q}%")
                    ->orWhere('siswa.nama_siswa', 'like', "%{$q}%")
                    ->orWhere('siswa.nis', 'like', "%{$q}%");
            });
        }

        $today = Carbon::today();

        match (strtolower((string) $request->input('status'))) {
            'dipinjam'     => $query->where('peminjaman.status', 'Dipinjam')->whereNull('pengembalian.id_peminjaman')->whereDate('peminjaman.tanggal_jatuh_tempo', '>=', $today),
            'terlambat'    => $query->where('peminjaman.status', 'Dipinjam')->whereNull('pengembalian.id_peminjaman')->whereDate('peminjaman.tanggal_jatuh_tempo', '<', $today),
            'dikembalikan' => $query->whereNotNull('pengembalian.id_peminjaman'),
            'menunggu'     => $query->where('peminjaman.status', 'Menunggu')->whereNull('pengembalian.id_peminjaman'),
            'ditolak'      => $query->where('peminjaman.status', 'Ditolak')->whereNull('pengembalian.id_peminjaman'),
            default        => null,
        };

        match ($request->input('tanggal')) {
            'hari-ini'  => $query->whereDate('peminjaman.tanggal_pinjam', $today),
            '7-hari'    => $query->whereDate('peminjaman.tanggal_pinjam', '>=', $today->copy()->subDays(6)),
            '30-hari'   => $query->whereDate('peminjaman.tanggal_pinjam', '>=', $today->copy()->subDays(29)),
            'bulan-ini' => $query->whereMonth('peminjaman.tanggal_pinjam', $today->month)->whereYear('peminjaman.tanggal_pinjam', $today->year),
            default     => null,
        };

        $peminjaman = $query->orderByDesc('peminjaman.id_peminjaman')->paginate(10)->withQueryString();

        $peminjaman->through(function ($r) {
            if ($r->tanggal_kembali) {
                $status = 'dikembalikan';
            } elseif ($r->status === 'Dipinjam' && Carbon::parse($r->tanggal_jatuh_tempo)->lt(Carbon::today())) {
                $status = 'terlambat';
            } else {
                $status = strtolower($r->status);
            }

            return (object) [
                'id'              => $r->id_peminjaman,
                'foto'            => null,
                'nama'            => $r->nama_siswa,
                'kelas'           => $r->kelas,
                'judul'           => $r->judul,
                'pengarang'       => $r->pengarang,
                'tanggal_pinjam'  => Carbon::parse($r->tanggal_pinjam),
                'jatuh_tempo'     => Carbon::parse($r->tanggal_jatuh_tempo),
                'tanggal_kembali' => $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali) : null,
                'status'          => $status,
            ];
        });

        return view('admin.peminjaman.index', compact('peminjaman'));
    }

    public function create()
    {
        $this->ensureAdmin();

        $anggota  = DB::table('siswa')->orderBy('nama_siswa')->get();
        $bukuList = DB::table('buku')->where('stok', '>', 0)->orderBy('judul')->get();

        return view('admin.peminjaman.create', compact('anggota', 'bukuList'));
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

        return redirect($this->areaRoute('peminjaman'))->with('success', 'Peminjaman baru berhasil dicatat.');
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

        if ($peminjaman->tanggal_kembali) {
            $peminjaman->status_efektif = 'Dikembalikan';
        } elseif ($peminjaman->status === 'Dipinjam' && Carbon::parse($peminjaman->tanggal_jatuh_tempo)->lt(Carbon::today())) {
            $peminjaman->status_efektif = 'Terlambat';
        } else {
            $peminjaman->status_efektif = $peminjaman->status;
        }

        return view('admin.peminjaman.show', compact('peminjaman'));
    }

    public function edit($id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        return view('admin.peminjaman.edit', compact('peminjaman'));
    }

    public function update(Request $request, $id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        $data = $request->validate([
            'tanggal_pinjam'      => ['required', 'date'],
            'tanggal_jatuh_tempo' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'status'              => ['required', 'in:Menunggu,Dipinjam,Dikembalikan,Ditolak'],
        ]);

        DB::table('peminjaman')->where('id_peminjaman', $id)->update($data);

        return redirect($this->areaRoute('peminjaman'))->with('success', 'Data peminjaman berhasil diperbarui.');
    }

    public function perpanjang($id)
    {
        $this->ensureAdmin();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();
        abort_if(! $peminjaman, 404);

        if ($peminjaman->status !== 'Dipinjam') {
            return back()->with('info', 'Peminjaman ini tidak dapat diperpanjang.');
        }

        $lama = Pengaturan::int('lama_pinjam_hari', 7);

        DB::table('peminjaman')->where('id_peminjaman', $id)->update([
            'tanggal_jatuh_tempo' => Carbon::parse($peminjaman->tanggal_jatuh_tempo)->addDays($lama)->toDateString(),
        ]);

        return back()->with('success', "Masa peminjaman diperpanjang {$lama} hari.");
    }

    public function pengembalian(Request $request)
    {
        $this->ensureAdmin();

        $query = DB::table('pengembalian as p')
            ->join('peminjaman as pm', 'pm.id_peminjaman', '=', 'p.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'pm.id_siswa')
            ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'pm.id_peminjaman')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
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
                's.kelas',
                'b.id_buku as buku_id',
                'b.judul',
                'b.pengarang'
            );

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('b.judul', 'like', "%{$q}%")
                    ->orWhere('s.nama_siswa', 'like', "%{$q}%")
                    ->orWhere('s.nis', 'like', "%{$q}%");
            });
        }

        match ($request->input('status')) {
            'terlambat'   => $query->whereColumn('p.tanggal_kembali', '>', 'pm.tanggal_jatuh_tempo'),
            'tepat_waktu' => $query->whereColumn('p.tanggal_kembali', '<=', 'pm.tanggal_jatuh_tempo'),
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

            return (object) [
                'id'              => $r->id,
                'tanggal_kembali' => $kembali,
                'tanggal_pinjam'  => Carbon::parse($r->tanggal_pinjam),
                'batas_kembali'   => $batas,
                'terlambat'       => $kembali->greaterThan($batas),
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

        return view('admin.pengembalian.index', compact('pengembalian', 'anggota'));
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
                'pm.tanggal_jatuh_tempo'
            )
            ->orderBy('s.nama_siswa')
            ->get();

        return view('admin.pengembalian.create', compact('peminjamanAktif'));
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

        DB::transaction(function () use ($data, $peminjaman, $kembali, $telat, $denda) {
            DB::table('pengembalian')->insert([
                'id_peminjaman'   => $peminjaman->id_peminjaman,
                'tanggal_kembali' => $kembali->toDateString(),
                'denda'           => $denda,
                'kategori'        => $data['kategori'] ?? ($telat > 0 ? 'Terlambat' : 'Tepat Waktu'),
            ]);

            $details = DB::table('detail_peminjaman')->where('id_peminjaman', $peminjaman->id_peminjaman)->get();
            foreach ($details as $d) {
                DB::table('buku')->where('id_buku', $d->id_buku)->increment('stok', $d->jumlah);
            }

            DB::table('peminjaman')
                ->where('id_peminjaman', $peminjaman->id_peminjaman)
                ->update(['status' => 'Dikembalikan']);
        });

        return redirect($this->areaRoute('pengembalian'))->with('success', 'Pengembalian berhasil dicatat.');
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
        $r->terlambat           = $kembali->greaterThan($batas);
        $r->lama_terlambat      = $r->terlambat ? $batas->diffInDays($kembali) : 0;

        return view('admin.pengembalian.show', ['pengembalian' => $r]);
    }

    public function kategori(Request $request)
    {
        $this->ensureAdmin();

        $query = DB::table('kategori_buku as k')
            ->select('k.id_kategori', 'k.nama_kategori', 'k.keterangan')
            ->selectSub(function ($sub) {
                $sub->from('buku as b')
                    ->whereColumn('b.id_kategori', 'k.id_kategori')
                    ->selectRaw('COUNT(*)');
            }, 'jumlah_buku');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('k.nama_kategori', 'like', "%{$q}%")
                    ->orWhere('k.keterangan', 'like', "%{$q}%");
            });
        }

        $kategori = $query->orderBy('k.nama_kategori')->paginate(10)->withQueryString();

        $stat = [
            'total'     => DB::table('kategori_buku')->count(),
            'terpakai'  => DB::table('buku')->distinct()->count('id_kategori'),
            'tanpa_buku'=> DB::table('kategori_buku')
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('buku')
                        ->whereColumn('buku.id_kategori', 'kategori_buku.id_kategori');
                })
                ->count(),
        ];

        return view('admin.kategori.index', compact('kategori', 'stat'));
    }

    public function kategoriCreate()
    {
        $this->ensureAdmin();

        return view('admin.kategori.create');
    }

    public function kategoriStore(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:kategori_buku,nama_kategori'],
            'keterangan'    => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('kategori_buku')->insert([
            'nama_kategori' => $data['nama_kategori'],
            'keterangan'    => $data['keterangan'] ?? null,
        ]);

        return redirect()->route('admin.kategori')->with('success', 'Kategori buku berhasil ditambahkan.');
    }

    public function kategoriEdit($id)
    {
        $this->ensureAdmin();

        $kategori = DB::table('kategori_buku')->where('id_kategori', $id)->first();
        abort_if(! $kategori, 404);

        return view('admin.kategori.edit', compact('kategori'));
    }

    public function kategoriUpdate(Request $request, $id)
    {
        $this->ensureAdmin();

        $kategori = DB::table('kategori_buku')->where('id_kategori', $id)->first();
        abort_if(! $kategori, 404);

        $data = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100', Rule::unique('kategori_buku', 'nama_kategori')->ignore($id, 'id_kategori')],
            'keterangan'    => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $id) {
            DB::table('kategori_buku')->where('id_kategori', $id)->update([
                'nama_kategori' => $data['nama_kategori'],
                'keterangan'    => $data['keterangan'] ?? null,
            ]);

            // Kolom buku.kategori adalah salinan nama kategori; jaga tetap sinkron.
            DB::table('buku')->where('id_kategori', $id)->update([
                'kategori' => $data['nama_kategori'],
            ]);
        });

        return redirect()->route('admin.kategori')->with('success', 'Kategori buku berhasil diperbarui.');
    }

    public function kategoriDestroy($id)
    {
        $this->ensureAdmin();

        $kategori = DB::table('kategori_buku')->where('id_kategori', $id)->first();
        abort_if(! $kategori, 404);

        if (DB::table('buku')->where('id_kategori', $id)->exists()) {
            return redirect()->route('admin.kategori')
                ->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh buku.');
        }

        DB::table('kategori_buku')->where('id_kategori', $id)->delete();

        return redirect()->route('admin.kategori')->with('success', 'Kategori buku berhasil dihapus.');
    }

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

        $stats = [
            ['color' => 'blue',   'icon' => 'bi-book',              'label' => 'Total Buku', 'value' => DB::table('buku')->count(), 'mini' => 'bi-collection', 'sub' => 'judul terdaftar'],
            ['color' => 'green',  'icon' => 'bi-check-circle',      'label' => 'Tersedia',   'value' => DB::table('buku')->where('stok', '>', 2)->count(), 'mini' => 'bi-bookmark-check', 'sub' => 'siap dipinjam'],
            ['color' => 'violet', 'icon' => 'bi-journal-arrow-up',  'label' => 'Dipinjam',   'value' => DB::table('detail_peminjaman')->join('peminjaman', 'peminjaman.id_peminjaman', '=', 'detail_peminjaman.id_peminjaman')->where('peminjaman.status', 'Dipinjam')->count(), 'mini' => 'bi-arrow-left-right', 'sub' => 'sedang beredar'],
            ['color' => 'red',    'icon' => 'bi-exclamation-circle', 'label' => 'Stok Habis', 'value' => DB::table('buku')->where('stok', '<=', 0)->count(), 'mini' => 'bi-box-seam', 'sub' => 'perlu restock'],
        ];

        $daftarKategori = DB::table('kategori_buku')->orderBy('nama_kategori')->pluck('nama_kategori')->all();
        $daftarTahun    = DB::table('buku')->whereNotNull('tahun_terbit')->distinct()->orderByDesc('tahun_terbit')->pluck('tahun_terbit')->all();
        $daftarStatus   = ['Tersedia', 'Hampir Habis', 'Habis'];

        return view('admin.buku.index', compact('buku', 'stats', 'daftarKategori', 'daftarTahun', 'daftarStatus'));
    }

    public function bukuCreate()
    {
        $this->ensureAdmin();

        $kategori = DB::table('kategori_buku')->orderBy('nama_kategori')->get();

        return view('admin.buku.create', compact('kategori'));
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

        return redirect()->route('admin.buku')->with('success', 'Buku berhasil ditambahkan.');
    }

    public function bukuEdit($id)
    {
        $this->ensureAdmin();

        $buku = DB::table('buku')->where('id_buku', $id)->first();
        abort_if(! $buku, 404);

        $kategori = DB::table('kategori_buku')->orderBy('nama_kategori')->get();

        return view('admin.buku.edit', compact('buku', 'kategori'));
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

        return redirect()->route('admin.buku')->with('success', 'Data buku berhasil diperbarui.');
    }

    public function bukuDestroy($id)
    {
        $this->ensureAdmin();

        $buku = DB::table('buku')->where('id_buku', $id)->first();
        abort_if(! $buku, 404);

        if (DB::table('detail_peminjaman')->where('id_buku', $id)->exists()) {
            return redirect()->route('admin.buku')
                ->with('error', 'Buku tidak dapat dihapus karena memiliki riwayat peminjaman.');
        }

        DB::table('buku')->where('id_buku', $id)->delete();

        return redirect()->route('admin.buku')->with('success', 'Buku berhasil dihapus.');
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

        $total = DB::table('siswa')->count();
        $baru  = DB::table('siswa')
            ->join('t_user', 't_user.id_user', '=', 'siswa.id_user')
            ->whereYear('t_user.created_at', now()->year)
            ->whereMonth('t_user.created_at', now()->month)
            ->count();

        $stats = [
            ['color' => 'stat-blue',   'icon' => 'bi-people-fill',       'label' => 'Total Anggota', 'value' => $total, 'delta' => $baru],
            ['color' => 'stat-green',  'icon' => 'bi-mortarboard-fill',  'label' => 'Siswa',         'value' => $total, 'delta' => $baru],
            ['color' => 'stat-orange', 'icon' => 'bi-person-badge-fill', 'label' => 'Guru',          'value' => 0,      'delta' => 0],
            ['color' => 'stat-purple', 'icon' => 'bi-person-workspace',  'label' => 'Staf TU',       'value' => 0,      'delta' => 0],
        ];

        $daftarJenis  = ['Siswa', 'Guru', 'Staf TU'];
        $daftarKelas  = DB::table('siswa')->distinct()->orderBy('kelas')->pluck('kelas')->all();
        $daftarStatus = ['Aktif', 'Nonaktif'];

        return view('admin.anggota.index', compact('anggota', 'stats', 'daftarJenis', 'daftarKelas', 'daftarStatus'));
    }

    private function warnaAvatar(string $nama): string
    {
        $palette = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#db2777'];

        return $palette[crc32($nama) % count($palette)];
    }

    public function anggotaCreate()
    {
        $this->ensureAdmin();

        return view('admin.anggota.create', ['daftarKelas' => $this->daftarKelas()]);
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

        return redirect($this->areaRoute('anggota'))->with('success', 'Anggota berhasil ditambahkan.');
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

        return view('admin.anggota.show', compact('anggota', 'riwayat'));
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

        return view('admin.anggota.edit', [
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

        return redirect($this->areaRoute('anggota'))->with('success', 'Data anggota berhasil diperbarui.');
    }

    public function anggotaDestroy($id)
    {
        $this->ensureAdmin();

        $anggota = DB::table('siswa')->where('id_siswa', $id)->first();
        abort_if(! $anggota, 404);

        if (DB::table('peminjaman')->where('id_siswa', $anggota->id_siswa)->where('status', 'Dipinjam')->exists()) {
            return redirect($this->areaRoute('anggota'))
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

        return redirect($this->areaRoute('anggota'))->with('success', 'Anggota berhasil dihapus.');
    }

    public function akun(Request $request)
    {
        $this->ensureAdminOnly();

        $query = DB::table('t_user as u')
            ->leftJoin('siswa as s', 's.id_user', '=', 'u.id_user')
            ->select('u.id_user as id', 'u.name', 'u.username', 'u.role', 'u.created_at', 'u.reset_requested_at', 'u.reset_approved_at', 's.id_siswa', 's.nama_siswa');

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('u.username', 'like', "%{$q}%")
                    ->orWhere('u.name', 'like', "%{$q}%")
                    ->orWhere('s.nama_siswa', 'like', "%{$q}%")
                    ->orWhere('s.nis', 'like', "%{$q}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('u.role', $role);
        }

        $akun = $query->orderBy('u.role')->orderBy('u.username')->paginate(10)->withQueryString();

        $akun->through(fn ($r) => (object) [
            'id'         => $r->id,
            'name'       => $r->name,
            'username'   => $r->username,
            'role'       => $r->role,
            'dibuat'     => $r->created_at ? Carbon::parse($r->created_at) : null,
            'id_siswa'   => $r->id_siswa,
            'nama_siswa' => $r->nama_siswa,
            'reset_status' => $r->reset_approved_at ? 'approved' : ($r->reset_requested_at ? 'pending' : 'none'),
        ]);

        $stat = [
            'total'      => DB::table('t_user')->count(),
            'admin'      => DB::table('t_user')->where('role', 'Admin')->count(),
            'pustakawan' => DB::table('t_user')->where('role', 'Pustakawan')->count(),
            'siswa'      => DB::table('t_user')->where('role', 'Siswa')->count(),
        ];

        // Daftar anggota untuk modal "Kaitkan Anggota".
        $anggotaList = DB::table('siswa as s')
            ->leftJoin('t_user as u', 'u.id_user', '=', 's.id_user')
            ->select('s.id_siswa', 's.nis', 's.nama_siswa', 's.kelas', 'u.username')
            ->orderBy('s.nama_siswa')
            ->get();

        return view('admin.akun.index', compact('akun', 'stat', 'anggotaList'));
    }

    public function akunCreate()
    {
        $this->ensureAdminOnly();

        // Daftar anggota untuk opsi "Terkait Anggota" (bisa dicari di form).
        $anggotaList = DB::table('siswa as s')
            ->leftJoin('t_user as u', 'u.id_user', '=', 's.id_user')
            ->select('s.id_siswa', 's.nis', 's.nama_siswa', 's.kelas', 'u.username')
            ->orderBy('s.nama_siswa')
            ->get();

        return view('admin.akun.create', compact('anggotaList'));
    }

    public function akunStore(Request $request)
    {
        $this->ensureAdminOnly();

        $data = $request->validate([
            'name'     => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', 'unique:t_user,username'],
            'password' => ['required', 'string', 'min:6'],
            'role'     => ['required', 'in:Admin,Pustakawan,Siswa'],
            'id_siswa' => ['nullable', 'integer', 'exists:siswa,id_siswa'],
        ]);

        DB::transaction(function () use ($data) {
            $idUser = DB::table('t_user')->insertGetId([
                'name'       => $data['name'] ?? null,
                'username'   => $data['username'],
                'password'   => Hash::make($data['password']),
                'role'       => $data['role'],
                'created_at' => now(),
            ]);

            // Tautkan akun baru ke anggota (data siswa) yang dipilih.
            if (! empty($data['id_siswa'])) {
                DB::table('siswa')->where('id_siswa', $data['id_siswa'])->update(['id_user' => $idUser]);
            }
        });

        return redirect()->route('admin.akun')->with('success', 'Akun berhasil ditambahkan.');
    }

    public function akunEdit($id)
    {
        $this->ensureAdminOnly();

        $akun = DB::table('t_user')->where('id_user', $id)->first();
        abort_if(! $akun, 404);

        $anggota = DB::table('siswa')
            ->where('id_user', $id)
            ->select('nama_siswa', 'nis', 'kelas')
            ->first();

        return view('admin.akun.edit', compact('akun', 'anggota'));
    }


    public function akunUpdate(Request $request, $id)
    {
        $this->ensureAdminOnly();

        $akun = DB::table('t_user')->where('id_user', $id)->first();
        abort_if(! $akun, 404);

        $data = $request->validate([
            'name'     => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', Rule::unique('t_user', 'username')->ignore($id, 'id_user')],
            'password' => ['nullable', 'string', 'min:6'],
            'role'     => ['required', 'in:Admin,Pustakawan,Siswa'],
        ]);

        $update = [
            'name'     => $data['name'] ?? null,
            'username' => $data['username'],
            'role'     => $data['role'],
        ];

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        DB::table('t_user')->where('id_user', $id)->update($update);

        return redirect()->route('admin.akun')->with('success', 'Akun berhasil diperbarui.');
    }

    public function akunDestroy($id)
    {
        $this->ensureAdminOnly();

        if ((int) $id === (int) Auth::id()) {
            return back()->with('info', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        $akun = DB::table('t_user')->where('id_user', $id)->first();
        abort_if(! $akun, 404);

        if (DB::table('siswa')->where('id_user', $id)->exists()) {
            return back()->with('info', 'Akun terhubung dengan data anggota. Hapus melalui menu Anggota.');
        }

        DB::table('t_user')->where('id_user', $id)->delete();

        return redirect()->route('admin.akun')->with('success', 'Akun berhasil dihapus.');
    }

    public function akunConfirmReset($id)
    {
        $this->ensureAdminOnly();

        $akun = DB::table('t_user')->where('id_user', $id)->first();
        abort_if(! $akun, 404);

        if (! $akun->reset_requested_at && ! $akun->reset_approved_at) {
            return back()->with('info', 'Tidak ada permintaan reset password untuk akun ini.');
        }

        // Tandai bahwa admin sudah mengonfirmasi. Saat pengguna login memakai
        // password default, ia akan diarahkan membuat password baru.
        DB::table('t_user')->where('id_user', $id)->update([
            'reset_approved_at' => now(),
        ]);

        return back()->with('success', 'Reset password untuk "' . $akun->username . '" dikonfirmasi. Pengguna akan diminta membuat password baru saat login.');
    }

    /**
     * Kaitkan (atau ganti) akun t_user dengan sebuah data siswa/anggota.
     * Bila akun sebelumnya sudah terkait dengan siswa lain, tautan lama
     * dipindahkan ke pemilik siswa tujuan agar tidak ada siswa tanpa akun.
     */
    public function akunLink(Request $request, $id)
    {
        $this->ensureAdminOnly();

        $akun = DB::table('t_user')->where('id_user', $id)->first();
        abort_if(! $akun, 404);

        $data = $request->validate([
            'id_siswa' => ['required', 'integer', 'exists:siswa,id_siswa'],
        ]);

        $target  = DB::table('siswa')->where('id_siswa', $data['id_siswa'])->first();
        $current = DB::table('siswa')->where('id_user', $id)->first();

        if ($current && (int) $current->id_siswa === (int) $target->id_siswa) {
            return back()->with('info', 'Akun "' . $akun->username . '" sudah terkait dengan anggota "' . $target->nama_siswa . '".');
        }

        DB::transaction(function () use ($target, $current, $id) {
            $pemilikLama = $target->id_user;

            // Pindahkan siswa tujuan ke akun ini.
            DB::table('siswa')->where('id_siswa', $target->id_siswa)->update(['id_user' => $id]);

            // Siswa yang sebelumnya dimiliki akun ini dialihkan ke pemilik lama
            // agar setiap siswa tetap memiliki satu akun.
            if ($current) {
                DB::table('siswa')->where('id_siswa', $current->id_siswa)->update(['id_user' => $pemilikLama]);
            }
        });

        return back()->with('success', 'Akun "' . $akun->username . '" berhasil dikaitkan dengan anggota "' . $target->nama_siswa . '".');
    }

    public function kelas(Request $request)
    {
        $this->ensureAdminOnly();

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
            'total'   => DB::table('kelas')->count(),
            'tingkat7' => DB::table('kelas')->where('tingkat', 'VII')->count(),
            'tingkat8' => DB::table('kelas')->where('tingkat', 'VIII')->count(),
            'tingkat9' => DB::table('kelas')->where('tingkat', 'IX')->count(),
        ];

        $daftarTingkat = DB::table('kelas')->whereNotNull('tingkat')->distinct()->orderBy('tingkat')->pluck('tingkat');

        return view('admin.kelas.index', compact('rows', 'stat', 'daftarTingkat'));
    }

    public function kelasCreate()
    {
        $this->ensureAdminOnly();

        return view('admin.kelas.create');
    }

    public function kelasStore(Request $request)
    {
        $this->ensureAdminOnly();

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

        return redirect()->route('admin.kelas')->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function kelasEdit($id)
    {
        $this->ensureAdminOnly();

        $kelas = DB::table('kelas')->where('id_kelas', $id)->first();
        abort_if(! $kelas, 404);

        return view('admin.kelas.edit', compact('kelas'));
    }

    public function kelasUpdate(Request $request, $id)
    {
        $this->ensureAdminOnly();

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

        return redirect()->route('admin.kelas')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function kelasDestroy($id)
    {
        $this->ensureAdminOnly();

        $kelas = DB::table('kelas')->where('id_kelas', $id)->first();
        abort_if(! $kelas, 404);

        $jumlah = DB::table('siswa')->where('kelas', $kelas->nama_kelas)->count();
        if ($jumlah > 0) {
            return back()->with('error', 'Kelas tidak dapat dihapus karena masih memiliki ' . $jumlah . ' anggota.');
        }

        DB::table('kelas')->where('id_kelas', $id)->delete();

        return redirect()->route('admin.kelas')->with('success', 'Kelas berhasil dihapus.');
    }

    public function denda(Request $request)
    {
        $this->ensureAdmin();

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
            ->selectSub(function ($q) {
                $q->from('detail_peminjaman as dp')
                    ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                    ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
                    ->selectRaw("GROUP_CONCAT(b.judul SEPARATOR ', ')");
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

        return view('admin.denda.index', compact('rows', 'stat'));
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

        return view('admin.denda.edit', compact('denda'));
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

        DB::table('pengembalian')->where('id_pengembalian', $id)->update([
            'denda'        => $data['denda'],
            'status_bayar' => $data['status_bayar'],
        ]);

        return redirect($this->areaRoute('denda'))->with('success', 'Data denda berhasil diperbarui.');
    }

    public function dendaToggle($id)
    {
        $this->ensureAdmin();

        $denda = DB::table('pengembalian')->where('id_pengembalian', $id)->first();
        abort_if(! $denda, 404);

        $baru = ($denda->status_bayar === 'Lunas') ? 'Belum Lunas' : 'Lunas';
        DB::table('pengembalian')->where('id_pengembalian', $id)->update(['status_bayar' => $baru]);

        return back()->with('success', 'Denda ditandai sebagai ' . $baru . '.');
    }

    public function statistik(Request $request)
    {
        $this->ensureAdmin();

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

    public function laporan(Request $request)
    {
        $this->ensureAdmin();

        // Periode
        $mulai = $request->filled('mulai')
            ? Carbon::parse($request->input('mulai'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $selesai = $request->filled('selesai')
            ? Carbon::parse($request->input('selesai'))->endOfDay()
            : now()->endOfDay();

        if ($mulai->gt($selesai)) {
            $mulai = $selesai->copy()->startOfDay();
        }

        // Tab
        $tab = $request->input('tab');
        if (! in_array($tab, ['peminjaman', 'pengembalian', 'keterlambatan', 'transaksi'], true)) {
            $tab = 'peminjaman';
        }

        $ringkasTransaksi = null;

        // Statistik
        $stat = [
            'buku'         => DB::table('buku')->count(),
            'anggota'      => DB::table('siswa')->count(),
            'peminjaman'   => DB::table('peminjaman')->whereBetween('tanggal_pinjam', [$mulai, $selesai])->count(),
            'pengembalian' => DB::table('pengembalian')->whereBetween('tanggal_kembali', [$mulai, $selesai])->count(),
        ];

        // Grafik batang peminjaman (7 / 30 hari)
        $grafik = in_array((int) $request->input('grafik'), [7, 30], true) ? (int) $request->input('grafik') : 7;

        $awalGrafik = $selesai->copy()->subDays($grafik - 1)->startOfDay();
        $counts = DB::table('peminjaman')
            ->selectRaw('DATE(tanggal_pinjam) as tgl, COUNT(*) as total')
            ->whereBetween('tanggal_pinjam', [$awalGrafik, $selesai->copy()->endOfDay()])
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
            ->whereBetween('p.tanggal_pinjam', [$mulai, $selesai])
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
        $bukuSub = fn ($q) => $q->from('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->whereColumn('dp.id_peminjaman', 'p.id_peminjaman')
            ->selectRaw("GROUP_CONCAT(b.judul SEPARATOR ', ')");

        // Tabel per tab
        if ($tab === 'pengembalian') {
            $kolom = ['Tanggal Pinjam', 'Tanggal Kembali'];

            $rows = DB::table('pengembalian as pg')
                ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->select('pg.id_pengembalian', 's.nama_siswa', 'p.tanggal_pinjam', 'pg.tanggal_kembali')
                ->selectSub($bukuSub, 'buku')
                ->whereBetween('pg.tanggal_kembali', [$mulai, $selesai])
                ->orderByDesc('pg.tanggal_kembali')
                ->orderByDesc('pg.id_pengembalian')
                ->paginate(10)
                ->withQueryString();

            $rows->through(fn ($r) => [
                'anggota' => $r->nama_siswa,
                'buku'    => $r->buku ?: '-',
                'tgl1'    => $r->tanggal_pinjam ? Carbon::parse($r->tanggal_pinjam)->translatedFormat('d M Y') : '-',
                'tgl2'    => $r->tanggal_kembali ? Carbon::parse($r->tanggal_kembali)->translatedFormat('d M Y') : '-',
                'status'  => 'Dikembalikan',
                'kelas'   => 'badge-success',
            ]);
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
                ->whereBetween('p.tanggal_pinjam', [$mulai, $selesai])
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
                    ->whereBetween('p.tanggal_pinjam', [$mulai, $selesai])
                    ->sum('pg.denda'),
                'belum' => (float) DB::table('pengembalian as pg')
                    ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
                    ->whereBetween('p.tanggal_pinjam', [$mulai, $selesai])
                    ->where('pg.status_bayar', 'Belum Lunas')
                    ->sum('pg.denda'),
            ];
        } else {
            $kolom = ['Tanggal Pinjam', 'Jatuh Tempo'];

            $rows = DB::table('peminjaman as p')
                ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
                ->select('p.id_peminjaman', 's.nama_siswa', 'p.tanggal_pinjam', 'p.tanggal_jatuh_tempo', 'p.status')
                ->selectSub($bukuSub, 'buku')
                ->whereBetween('p.tanggal_pinjam', [$mulai, $selesai])
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

        return view('admin.laporan.index', compact(
            'tab', 'mulai', 'selesai', 'stat', 'grafik', 'grafikLabel', 'grafikData', 'kategori', 'kolom', 'rows', 'ringkasTransaksi'
        ));
    }

    private function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Dikembalikan' => 'badge-success',
            'Dipinjam'     => 'badge-info',
            'Menunggu'     => 'badge-warning',
            'Ditolak'      => 'badge-danger',
            'Terlambat'    => 'badge-danger',
            default        => 'badge-secondary',
        };
    }
}
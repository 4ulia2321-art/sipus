<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Rekap Data — ringkasan agregat peminjaman.
 *
 * Semua angka dihitung dari tabel yang sudah ada tanpa membuat tabel baru.
 * Filter diterapkan pada satu basis query yang sama agar kartu ringkasan dan
 * tabel selalu konsisten. Query detail memakai subquery agregat per transaksi
 * supaya JOIN ke detail_peminjaman tidak menggandakan jumlah transaksi.
 */
class RekapController extends Controller
{
    /**
     * Urutan status yang ditampilkan lebih dulu. Status lain yang muncul di
     * database tetap ditampilkan setelah daftar ini.
     */
    private const URUTAN_STATUS = [
        'Menunggu',
        'Dipinjam',
        'Menunggu Pembayaran',
        'Dikembalikan',
        'Ditolak',
    ];

    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->role === 'Admin', 403);

        $opsiKelas = DB::table('kelas')
            ->select('id_kelas', 'nama_kelas')
            ->orderBy('id_kelas')
            ->get();

        $opsiKategori = DB::table('kategori_buku')
            ->select('id_kategori', 'nama_kategori')
            ->orderBy('nama_kategori')
            ->get();

        // Status diambil dari data nyata, bukan daftar status yang dianggap
        // seharusnya ada. Status "Disetujui" tidak dipaksakan muncul.
        $opsiStatus = DB::table('peminjaman')
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->map(fn ($status) => trim((string) $status))
            ->filter()
            ->unique()
            ->sortBy(fn ($status) => $this->peringkatStatus($status))
            ->values();

        [$mulai, $selesai] = $this->rentangTanggal($request);

        $idKelas = $this->idPositif($request->input('kelas'));
        if ($idKelas !== null && ! $opsiKelas->contains('id_kelas', $idKelas)) {
            $idKelas = null;
        }

        $idKategori = $this->idPositif($request->input('kategori'));
        if ($idKategori !== null && ! $opsiKategori->contains('id_kategori', $idKategori)) {
            $idKategori = null;
        }

        $status = $request->input('status');
        $status = is_string($status) && $opsiStatus->contains($status) ? $status : null;

        $filter = [
            'kelas' => $idKelas,
            'kategori' => $idKategori,
            'status' => $status,
            'mulai' => $mulai?->format('Y-m-d'),
            'selesai' => $selesai?->format('Y-m-d'),
        ];

        $adaFilter = $idKelas !== null
            || $idKategori !== null
            || $status !== null
            || $mulai !== null
            || $selesai !== null;

        // Basis transaksi sengaja hanya JOIN ke siswa. Detail buku tidak
        // di-JOIN di sini agar satu transaksi tetap dihitung satu kali.
        $base = DB::table('peminjaman as p')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa');

        $this->terapkanFilter($base, $filter);

        $totalTransaksi = (int) (clone $base)->count('p.id_peminjaman');

        $totalKelas = (int) (clone $base)
            ->selectRaw('COUNT(DISTINCT s.id_kelas) as total')
            ->value('total');

        // Basis detail dipakai untuk SUM(jumlah) dan rekap kategori.
        $detail = DB::table('detail_peminjaman as dp')
            ->join('peminjaman as p', 'p.id_peminjaman', '=', 'dp.id_peminjaman')
            ->join('siswa as s', 's.id_siswa', '=', 'p.id_siswa')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku');

        $this->terapkanFilter($detail, $filter, false);

        if ($filter['kategori'] !== null) {
            $detail->where('b.id_kategori', $filter['kategori']);
        }

        $ringkasDetail = (clone $detail)
            ->selectRaw('COALESCE(SUM(dp.jumlah), 0) as total_buku')
            ->selectRaw('COUNT(DISTINCT b.id_kategori) as total_kategori')
            ->first();

        $totalBuku = (int) ($ringkasDetail->total_buku ?? 0);
        $totalKategori = (int) ($ringkasDetail->total_kategori ?? 0);

        // Satu baris per transaksi berisi total salinan bukunya. Subquery ini
        // yang dipakai saat per kelas/status, sehingga COUNT transaksi tidak
        // berlipat ketika satu peminjaman punya beberapa detail buku.
        $bukuPerTransaksi = (clone $detail)
            ->select('dp.id_peminjaman')
            ->selectRaw('SUM(dp.jumlah) as total_buku')
            ->groupBy('dp.id_peminjaman');

        $perKelas = (clone $base)
            ->leftJoinSub(clone $bukuPerTransaksi, 'd', 'd.id_peminjaman', '=', 'p.id_peminjaman')
            ->selectRaw('s.kelas as label')
            ->selectRaw('COUNT(DISTINCT p.id_peminjaman) as total_transaksi')
            ->selectRaw('COALESCE(SUM(d.total_buku), 0) as total_buku')
            ->groupBy('s.id_kelas', 's.kelas')
            ->orderBy('s.id_kelas')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ?: '-',
                'total_transaksi' => (int) $row->total_transaksi,
                'total_buku' => (int) $row->total_buku,
            ])
            ->values();

        $perKategori = (clone $detail)
            ->leftJoin('kategori_buku as k', 'k.id_kategori', '=', 'b.id_kategori')
            ->selectRaw("COALESCE(k.nama_kategori, 'Tanpa Kategori') as label")
            ->selectRaw('COUNT(DISTINCT p.id_peminjaman) as total_transaksi')
            ->selectRaw('COALESCE(SUM(dp.jumlah), 0) as total_buku')
            ->groupBy('k.id_kategori', 'k.nama_kategori')
            ->orderByDesc('total_buku')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ?: 'Tanpa Kategori',
                'total_transaksi' => (int) $row->total_transaksi,
                'total_buku' => (int) $row->total_buku,
            ])
            ->values();

        $perStatus = (clone $base)
            ->leftJoinSub(clone $bukuPerTransaksi, 'd', 'd.id_peminjaman', '=', 'p.id_peminjaman')
            ->selectRaw('p.status as label')
            ->selectRaw('COUNT(DISTINCT p.id_peminjaman) as total_transaksi')
            ->selectRaw('COALESCE(SUM(d.total_buku), 0) as total_buku')
            ->groupBy('p.status')
            ->get()
            ->map(function ($row) use ($totalTransaksi) {
                $jumlah = (int) $row->total_transaksi;

                return [
                    'label' => $row->label ?: '-',
                    'total_transaksi' => $jumlah,
                    'total_buku' => (int) $row->total_buku,
                    'persen' => $totalTransaksi > 0
                        ? round($jumlah / $totalTransaksi * 100, 1)
                        : 0,
                ];
            })
            ->sortBy(fn ($row) => $this->peringkatStatus($row['label']))
            ->values();

        $ringkasan = [
            'total_transaksi' => $totalTransaksi,
            'total_buku' => $totalBuku,
            'total_kelas' => $totalKelas,
            'total_kategori' => $totalKategori,
            'rata_buku' => $totalTransaksi > 0
                ? round($totalBuku / $totalTransaksi, 1)
                : 0,
        ];

        $periodeLabel = match (true) {
            $mulai !== null && $selesai !== null => $mulai->translatedFormat('d M Y')
                .' – '.$selesai->translatedFormat('d M Y'),
            $mulai !== null => 'Sejak '.$mulai->translatedFormat('d M Y'),
            $selesai !== null => 'Sampai '.$selesai->translatedFormat('d M Y'),
            default => 'Semua periode',
        };

        $badgeStatus = [
            'Menunggu' => 'badge-warning',
            'Dipinjam' => 'badge-info',
            'Menunggu Pembayaran' => 'badge-warning',
            'Dikembalikan' => 'badge-success',
            'Ditolak' => 'badge-danger',
            'Terlambat' => 'badge-danger',
        ];

        return view('admin.rekap.index', compact(
            'filter',
            'opsiKelas',
            'opsiKategori',
            'opsiStatus',
            'ringkasan',
            'perKelas',
            'perKategori',
            'perStatus',
            'periodeLabel',
            'adaFilter',
            'badgeStatus'
        ));
    }

    /**
     * Filter yang sama untuk semua query transaksi.
     *
     * Filter kategori memakai EXISTS, bukan JOIN, agar transaksi yang punya
     * beberapa buku dalam kategori yang sama tidak terlipat ganda.
     */
    private function terapkanFilter(Builder $query, array $filter, bool $denganKategori = true): void
    {
        if ($filter['kelas'] !== null) {
            $query->where('s.id_kelas', $filter['kelas']);
        }

        if ($filter['status'] !== null) {
            $query->where('p.status', $filter['status']);
        }

        if ($filter['mulai'] !== null) {
            $query->where('p.tanggal_pinjam', '>=', $filter['mulai']);
        }

        if ($filter['selesai'] !== null) {
            $query->where('p.tanggal_pinjam', '<=', $filter['selesai']);
        }

        if ($denganKategori && $filter['kategori'] !== null) {
            $query->whereExists(function (Builder $sub) use ($filter) {
                $sub->selectRaw('1')
                    ->from('detail_peminjaman as dp_filter')
                    ->join('buku as b_filter', 'b_filter.id_buku', '=', 'dp_filter.id_buku')
                    ->whereColumn('dp_filter.id_peminjaman', 'p.id_peminjaman')
                    ->where('b_filter.id_kategori', $filter['kategori']);
            });
        }
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function rentangTanggal(Request $request): array
    {
        $mulai = $this->parseTanggal($request->input('mulai'), false);
        $selesai = $this->parseTanggal($request->input('selesai'), true);

        // Bila pengguna membalik urutan tanggal, tukar agar filter tetap
        // masuk akal daripada menampilkan hasil kosong.
        if ($mulai !== null && $selesai !== null && $mulai->gt($selesai)) {
            $awal = $selesai->copy()->startOfDay();
            $selesai = $mulai->copy()->endOfDay();
            $mulai = $awal;
        }

        return [$mulai, $selesai];
    }

    private function parseTanggal(mixed $nilai, bool $akhirHari): ?Carbon
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return null;
        }

        $nilai = trim($nilai);

        try {
            $tanggal = Carbon::createFromFormat('Y-m-d', $nilai);
        } catch (\Throwable) {
            return null;
        }

        if (! $tanggal instanceof Carbon || $tanggal->format('Y-m-d') !== $nilai) {
            return null;
        }

        return $akhirHari ? $tanggal->endOfDay() : $tanggal->startOfDay();
    }

    private function idPositif(mixed $nilai): ?int
    {
        if (! is_scalar($nilai) || trim((string) $nilai) === '') {
            return null;
        }

        $id = filter_var($nilai, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : (int) $id;
    }

    private function peringkatStatus(?string $status): int
    {
        $posisi = array_search($status, self::URUTAN_STATUS, true);

        return $posisi === false ? count(self::URUTAN_STATUS) : $posisi;
    }
}

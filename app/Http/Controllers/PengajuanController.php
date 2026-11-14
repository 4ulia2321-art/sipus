<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PengajuanController extends Controller
{
    public function index(Request $request)
    {
        return $this->daftarPengajuan($request, false);
    }

    /**
     * Halaman khusus Pustakawan: hanya pengajuan berstatus "Menunggu"
     * yang perlu disetujui atau ditolak.
     */
    public function persetujuan(Request $request)
    {
        $this->ensurePustakawan();

        return $this->daftarPengajuan($request, true);
    }

    private function daftarPengajuan(Request $request, bool $hanyaMenunggu)
    {
        // Daftar buku yang dapat diajukan siswa dipisahkan per kategori.
        // Hanya kategori Pelajaran dan Cerita yang ditampilkan agar buku
        // pendamping (Pelajaran Lain) tidak tercampur di halaman pengajuan.
        $bukuList = DB::table('buku')
            ->join('kategori_buku', 'kategori_buku.id_kategori', '=', 'buku.id_kategori')
            ->whereIn('kategori_buku.nama_kategori', ['Pelajaran', 'Cerita'])
            ->where('buku.stok', '>', 0)
            ->selectRaw("buku.id_buku as id, buku.judul, buku.pengarang, buku.cover, kategori_buku.nama_kategori as kategori, 'tersedia' as status")
            ->orderByRaw("CASE kategori_buku.nama_kategori WHEN 'Pelajaran' THEN 1 WHEN 'Cerita' THEN 2 ELSE 3 END")
            ->orderBy('buku.judul')
            ->get()
            ->groupBy('kategori');

        $query = DB::table('peminjaman')
            ->join('siswa', 'siswa.id_siswa', '=', 'peminjaman.id_siswa')
            ->leftJoin('t_user as akun', 'akun.id_user', '=', 'siswa.id_user')
            ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
            ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
            ->select(
                'peminjaman.id_peminjaman',
                'peminjaman.tanggal_pinjam',
                'peminjaman.tanggal_jatuh_tempo',
                'peminjaman.status',
                'siswa.nis',
                'siswa.nama_siswa',
                'siswa.kelas',
                'buku.judul',
                // Relasi: peminjaman.id_siswa -> siswa.id_siswa -> siswa.id_user -> t_user.id_user.
                // Nama pemohon diambil dari data siswa yang terhubung ke akun
                // login, lalu fallback ke nama akun / username.
                DB::raw("COALESCE(NULLIF(TRIM(siswa.nama_siswa), ''), NULLIF(TRIM(akun.name), ''), akun.username) as nama_pemohon")
            );

        $isAdmin = in_array(Auth::user()->role, ['Admin', 'Pustakawan'], true);

        if (! $isAdmin) {
            $siswaId = DB::table('siswa')->where('id_user', Auth::id())->value('id_siswa');
            $query->where('peminjaman.id_siswa', $siswaId);
        }

        if ($hanyaMenunggu) {
            $query->where('peminjaman.status', 'Menunggu');
        }

        $pengajuan = $query->orderByDesc('peminjaman.id_peminjaman')->get();

        $judulHalaman = $hanyaMenunggu ? 'Persetujuan Peminjaman' : 'Pengajuan Peminjaman';
        $deskripsiHalaman = $hanyaMenunggu
            ? 'Setujui atau tolak pengajuan peminjaman yang masuk.'
            : ($isAdmin
                ? 'Pantau seluruh pengajuan peminjaman anggota perpustakaan.'
                : 'Pilih buku yang ingin dipinjam dan pantau status pengajuanmu.');

        return view('admin.pengajuan.index', compact(
            'bukuList', 'pengajuan', 'isAdmin', 'hanyaMenunggu', 'judulHalaman', 'deskripsiHalaman'
        ));
    }

    public function create($id)
    {
        $buku = DB::table('buku')
            ->selectRaw("id_buku as id, judul, pengarang, kategori, tahun_terbit, lokasi_rak, cover, NULL as jumlah_halaman, CASE WHEN stok > 0 THEN 'tersedia' ELSE 'dipinjam' END as status")
            ->where('id_buku', $id)
            ->first();

        abort_if(! $buku, 404);

        // Identitas pemohon diambil otomatis dari akun siswa yang sedang login.
        $siswa = DB::table('siswa')->where('id_user', Auth::id())->first();

        $lamaPinjam = Pengaturan::int('lama_pinjam_hari', 7);
        $tanggalPinjam = Carbon::today();
        $tanggalKembali = $tanggalPinjam->copy()->addDays($lamaPinjam);

        return view('pengajuan.create', compact('buku', 'lamaPinjam', 'tanggalPinjam', 'tanggalKembali', 'siswa'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'buku_id' => ['required', 'integer'],
            'nis'     => ['nullable', 'string'],
        ]);

        // Siswa hanya boleh mengajukan untuk dirinya sendiri. NIS dari form
        // tidak dipakai untuk role Siswa agar tidak bisa meminjam atas nama orang lain.
        $user = Auth::user();

        if ($user->role === 'Siswa') {
            $siswa = DB::table('siswa')->where('id_user', $user->id_user)->first();
        } else {
            $siswa = DB::table('siswa')->where('nis', $data['nis'] ?? '')->first();
        }

        if (! $siswa) {
            return back()->with('error', 'Data siswa tidak ditemukan.')->withInput();
        }

        $buku = DB::table('buku')->where('id_buku', $data['buku_id'])->first();

        if (! $buku || $buku->stok <= 0) {
            return back()->with('error', 'Buku tidak tersedia untuk dipinjam.')->withInput();
        }

        $lamaPinjam = Pengaturan::int('lama_pinjam_hari', 7);
        $tanggalPinjam = Carbon::today();
        $tanggalKembali = $tanggalPinjam->copy()->addDays($lamaPinjam);

        DB::transaction(function () use ($data, $siswa, $tanggalPinjam, $tanggalKembali) {
            $idPeminjaman = DB::table('peminjaman')->insertGetId([
                'id_siswa'            => $siswa->id_siswa,
                'tanggal_pinjam'      => $tanggalPinjam,
                'tanggal_jatuh_tempo' => $tanggalKembali,
                'status'              => 'Menunggu',
            ]);

            DB::table('detail_peminjaman')->insert([
                'id_peminjaman' => $idPeminjaman,
                'id_buku'       => $data['buku_id'],
                'jumlah'        => 1,
            ]);
        });

        return redirect()->route('pengajuan')
            ->with('success', 'Pengajuan peminjaman berhasil dikirim. Menunggu persetujuan pustakawan.');
    }

    public function approve($id)
    {
        $this->ensurePustakawan();

        $hasil = DB::transaction(function () use ($id) {
            $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->lockForUpdate()->first();

            if (! $peminjaman || $peminjaman->status !== 'Menunggu') {
                return 'invalid';
            }

            $details = DB::table('detail_peminjaman')->where('id_peminjaman', $id)->get();

            if ($details->isEmpty()) {
                return 'invalid';
            }

            // Validasi stok seluruh buku sebelum status diubah.
            foreach ($details as $detail) {
                $buku = DB::table('buku')->where('id_buku', $detail->id_buku)->lockForUpdate()->first();

                if (! $buku || $buku->stok < $detail->jumlah) {
                    return 'stok:' . ($buku->judul ?? 'buku');
                }
            }

            foreach ($details as $detail) {
                DB::table('buku')
                    ->where('id_buku', $detail->id_buku)
                    ->decrement('stok', $detail->jumlah);
            }

            DB::table('peminjaman')
                ->where('id_peminjaman', $id)
                ->update(['status' => 'Dipinjam']);

            return 'ok';
        });

        if ($hasil === 'ok') {
            return back()->with('success', 'Pengajuan disetujui, buku telah dipinjamkan.');
        }

        if (str_starts_with($hasil, 'stok:')) {
            return back()->with('error', 'Stok buku "' . substr($hasil, 5) . '" tidak mencukupi untuk disetujui.');
        }

        return back()->with('error', 'Pengajuan tidak dapat disetujui karena statusnya sudah diproses.');
    }

    public function reject($id)
    {
        $this->ensurePustakawan();

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $id)->first();

        if (! $peminjaman || $peminjaman->status !== 'Menunggu') {
            return back()->with('error', 'Pengajuan tidak dapat ditolak karena statusnya sudah diproses.');
        }

        DB::table('peminjaman')->where('id_peminjaman', $id)->update(['status' => 'Ditolak']);

        return back()->with('success', 'Pengajuan ditolak.');
    }

    private function ensurePustakawan(): void
    {
        abort_unless(Auth::user()?->role === 'Pustakawan', 403);
    }
}

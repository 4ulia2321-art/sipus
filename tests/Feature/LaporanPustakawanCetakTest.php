<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LaporanPustakawanCetakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatUser(string $role, string $username): TUser
    {
        $id = DB::table('t_user')->insertGetId([
            'username'   => $username,
            'name'       => ucfirst($username),
            'password'   => Hash::make('secret'),
            'role'       => $role,
            'created_at' => now(),
        ]);

        return TUser::findOrFail($id);
    }

    private function buatSiswa(TUser $user, string $nis = '5001'): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa Laporan',
            'kelas'      => 'IX A',
        ]);
    }

    private function buatBuku(string $judul): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji laporan',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'LAP-' . uniqid(),
            'judul'        => $judul,
            'pengarang'    => 'Penulis Uji',
            'penerbit'     => 'Penerbit Uji',
            'tahun_terbit' => 2026,
            'kategori'     => 'Pelajaran',
            'lokasi_rak'   => 'R1',
            'stok'         => 5,
            'created_at'   => now(),
        ]);
    }

    private function buatPeminjaman(int $idSiswa, int $idBuku, string $tanggalPinjam, string $jatuhTempo): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => $tanggalPinjam,
            'tanggal_jatuh_tempo' => $jatuhTempo,
            'status'              => 'Dipinjam',
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    private function buatPengembalian(int $idPeminjaman, string $tanggalKembali, int $denda = 0, string $statusBayar = 'Lunas'): void
    {
        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => $tanggalKembali,
            'denda'           => $denda,
            'kategori'        => $denda > 0 ? 'Terlambat' : 'Tepat Waktu',
            'status_bayar'    => $statusBayar,
        ]);
    }

    /**
     * @return array{0: TUser, 1: array<string, int>}
     */
    private function siapkanData(): array
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanlaporan');
        $siswaUser  = $this->buatUser('Siswa', 'siswalaporan');
        $idSiswa    = $this->buatSiswa($siswaUser);

        $peminjaman = [
            'hari_ini' => $this->buatPeminjaman(
                $idSiswa,
                $this->buatBuku('Buku Laporan Hari Ini'),
                '2026-11-11',
                '2026-11-18'
            ),
            'bulan_ini' => $this->buatPeminjaman(
                $idSiswa,
                $this->buatBuku('Buku Laporan Bulan Ini'),
                '2026-11-02',
                '2026-11-09'
            ),
            'bulan_lalu' => $this->buatPeminjaman(
                $idSiswa,
                $this->buatBuku('Buku Laporan Bulan Lalu'),
                '2026-10-15',
                '2026-10-22'
            ),
            'tahun_lalu' => $this->buatPeminjaman(
                $idSiswa,
                $this->buatBuku('Buku Laporan Tahun Lalu'),
                '2025-12-20',
                '2025-12-27'
            ),
        ];

        // Pengembalian dibuat berbeda bulan agar filter tanggal kembali teruji.
        // Denda dipakai untuk menguji rekapan denda pada laporan.
        $this->buatPengembalian($peminjaman['bulan_ini'], '2026-11-05', 5000, 'Belum Lunas');
        $this->buatPengembalian($peminjaman['bulan_lalu'], '2026-10-20', 3000, 'Lunas');
        $this->buatPengembalian($peminjaman['tahun_lalu'], '2025-12-25');

        return [$pustakawan, $peminjaman];
    }

    public function test_preset_harian_bulanan_tahunan_memfilter_data_laporan(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?periode=harian')
            ->assertOk()
            ->assertSee('Buku Laporan Hari Ini')
            ->assertDontSee('Buku Laporan Bulan Ini')
            ->assertDontSee('Buku Laporan Bulan Lalu')
            ->assertDontSee('Buku Laporan Tahun Lalu');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?periode=bulanan')
            ->assertOk()
            ->assertSee('Harian')
            ->assertSee('Bulanan')
            ->assertSee('Tahunan')
            ->assertSee('Buku Laporan Hari Ini')
            ->assertSee('Buku Laporan Bulan Ini')
            ->assertDontSee('Buku Laporan Bulan Lalu')
            ->assertDontSee('Buku Laporan Tahun Lalu');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?periode=tahunan')
            ->assertOk()
            ->assertSee('Buku Laporan Hari Ini')
            ->assertSee('Buku Laporan Bulan Ini')
            ->assertSee('Buku Laporan Bulan Lalu')
            ->assertDontSee('Buku Laporan Tahun Lalu');
    }

    public function test_tombol_cetak_membawa_tanggal_periode_terpilih(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?periode=bulanan')
            ->assertOk()
            ->assertSee('Cetak Laporan')
            ->assertSee('laporan/cetak?tab=peminjaman', false)
            ->assertSee('periode=bulanan', false)
            ->assertSee('mulai=2026-11-01', false)
            ->assertSee('selesai=2026-11-30', false);
    }

    public function test_halaman_cetak_peminjaman_menampilkan_periode_dan_seluruh_data(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/cetak?tab=peminjaman&periode=kustom&mulai=2026-10-01&selesai=2026-10-31')
            ->assertOk()
            ->assertSee('Laporan Peminjaman Buku')
            ->assertSee('Periode: 01 Oktober 2026 s.d. 31 Oktober 2026')
            ->assertSee('Buku Laporan Bulan Lalu')
            ->assertDontSee('Buku Laporan Hari Ini')
            ->assertDontSee('Buku Laporan Tahun Lalu')
            ->assertSee('name="mulai"', false)
            ->assertSee('name="selesai"', false)
            ->assertSee('Cetak Sekarang');
    }

    public function test_halaman_cetak_pengembalian_memakai_tanggal_kembali(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/cetak?tab=pengembalian&periode=kustom&mulai=2026-11-01&selesai=2026-11-30')
            ->assertOk()
            ->assertSee('Laporan Pengembalian Buku')
            ->assertSee('Periode: 01 November 2026 s.d. 30 November 2026')
            ->assertSee('Buku Laporan Bulan Ini')
            ->assertSee('05 Nov 2026')
            ->assertDontSee('Buku Laporan Bulan Lalu')
            ->assertDontSee('Buku Laporan Tahun Lalu');
    }

    public function test_halaman_cetak_menampilkan_kop_sekolah_dan_logo(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/cetak?tab=peminjaman&periode=bulanan')
            ->assertOk()
            ->assertSee('PEMERINTAH KABUPATEN BANDUNG')
            ->assertSee('DINAS PENDIDIKAN')
            ->assertSee('SMP NEGERI 1 PASIRJAMBU')
            ->assertSee('Perpustakaan SMP Negeri 01 Pasirjambu')
            ->assertSee('logo-smpn1-pasirjambu.png', false)
            ->assertSee('logo-sipus.png', false);
    }

    public function test_cetak_menampilkan_semua_baris_tanpa_pagination(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanlaporan2');
        $siswaUser  = $this->buatUser('Siswa', 'siswalaporan2');
        $idSiswa    = $this->buatSiswa($siswaUser, '5002');

        for ($i = 1; $i <= 12; $i++) {
            $this->buatPeminjaman(
                $idSiswa,
                $this->buatBuku('Buku Cetak Massal ' . $i),
                '2026-11-03',
                '2026-11-10'
            );
        }

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/cetak?tab=peminjaman&periode=kustom&mulai=2026-11-03&selesai=2026-11-03')
            ->assertOk()
            ->assertSee('Buku Cetak Massal 1')
            ->assertSee('Buku Cetak Massal 12')
            ->assertSee('Jumlah data:')
            ->assertSee('12');
    }

    public function test_laporan_menampilkan_rekap_denda_dan_tanpa_label_terbaru(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?tab=peminjaman&periode=bulanan')
            ->assertOk()
            ->assertSee('Rekap Peminjaman')
            ->assertSee('Total Peminjaman')
            ->assertSee('Total Denda')
            ->assertSee('Denda Lunas')
            ->assertSee('Denda Belum Lunas')
            ->assertSee('Rp 5.000')
            ->assertSee('1 transaksi berdenda')
            ->assertDontSee('Peminjaman Terbaru')
            ->assertDontSee('Pengembalian Terbaru')
            // Denda Oktober tidak boleh ikut periode November.
            ->assertDontSee('Rp 3.000');

        // Rekap mengikuti tab dan periode yang dipilih.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?tab=pengembalian&periode=kustom&mulai=2026-10-01&selesai=2026-10-31')
            ->assertOk()
            ->assertSee('Rekap Pengembalian')
            ->assertSee('Total Pengembalian')
            ->assertSee('Rp 3.000')
            ->assertSee('1 transaksi berdenda')
            ->assertDontSee('Rp 5.000');
    }

    public function test_rekap_denda_terisi_otomatis_untuk_pengembalian_pada_periode(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-denda-lintas');
        $siswaUser  = $this->buatUser('Siswa', 'siswa-denda-lintas');
        $idSiswa    = $this->buatSiswa($siswaUser, '5010');

        // Dua peminjaman Oktober yang baru dikembalikan pada November.
        $belumLunas = $this->buatPeminjaman(
            $idSiswa,
            $this->buatBuku('Buku Denda Lintas Bulan'),
            '2026-10-08',
            '2026-10-15'
        );
        $lunas = $this->buatPeminjaman(
            $idSiswa,
            $this->buatBuku('Buku Denda Sudah Lunas'),
            '2026-10-10',
            '2026-10-17'
        );

        $this->buatPengembalian($belumLunas, '2026-11-11', 34000, 'Belum Lunas');
        $this->buatPengembalian($lunas, '2026-11-12', 6000, 'Lunas');

        // Ringkasan denda harus terisi walau tidak ada peminjaman baru di November.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/cetak?tab=peminjaman&periode=kustom&mulai=2026-11-01&selesai=2026-11-30')
            ->assertOk()
            ->assertSee('Total Denda')
            ->assertSee('Rp 40.000')
            ->assertSee('Denda Lunas')
            ->assertSee('Rp 6.000')
            ->assertSee('Denda Belum Lunas')
            ->assertSee('Rp 34.000');

        // Halaman laporan memakai ringkasan yang sama.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?tab=peminjaman&periode=kustom&mulai=2026-11-01&selesai=2026-11-30')
            ->assertOk()
            ->assertSee('Rp 40.000')
            ->assertSee('Rp 6.000')
            ->assertSee('Rp 34.000');
    }

    public function test_cetak_menampilkan_rekap_denda_dan_status_bayar_per_baris(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/cetak?tab=pengembalian&periode=bulanan')
            ->assertOk()
            ->assertSee('Rekap Pengembalian')
            ->assertSee('Total Pengembalian')
            ->assertSee('Total Denda')
            ->assertSee('Denda Belum Lunas')
            ->assertSee('Rp 5.000')
            ->assertSee('denda-status', false)
            ->assertSee('Belum Lunas')
            ->assertDontSee('Rp 3.000');
    }

    public function test_siswa_tidak_bisa_membuka_halaman_cetak_pustakawan(): void
    {
        $siswa = $this->buatUser('Siswa', 'siswacetak');

        $this->actingAs($siswa)
            ->get('/pustakawan/laporan/cetak')
            ->assertRedirect('/dashboard');
    }
}

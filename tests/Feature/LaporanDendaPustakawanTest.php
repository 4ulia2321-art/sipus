<?php

namespace Tests\Feature;

use App\Models\TUser;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LaporanDendaPustakawanTest extends TestCase
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

    private function buatSiswa(TUser $user, string $nis = '6001', string $kelas = 'IX A'): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa Denda ' . $nis,
            'kelas'      => $kelas,
        ]);
    }

    private function buatBuku(string $judul): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji denda',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'DND-' . uniqid(),
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

    private function buatPeminjaman(
        int $idSiswa,
        int $idBuku,
        string $tanggalPinjam,
        string $jatuhTempo,
        string $status = 'Dikembalikan'
    ): int {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => $tanggalPinjam,
            'tanggal_jatuh_tempo' => $jatuhTempo,
            'status'              => $status,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    private function buatPengembalian(
        int $idPeminjaman,
        string $tanggalKembali,
        int $denda,
        string $statusBayar = 'Belum Lunas'
    ): int {
        return DB::table('pengembalian')->insertGetId([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => $tanggalKembali,
            'denda'           => $denda,
            'kategori'        => $denda > 0 ? 'Terlambat' : 'Tepat Waktu',
            'status_bayar'    => $statusBayar,
        ]);
    }

    public function test_tab_denda_tersedia_di_halaman_laporan(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-tab-denda');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan')
            ->assertOk()
            ->assertSee('Denda')
            ->assertSee(route('pustakawan.laporan.denda'), false);
    }

    public function test_laporan_denda_menampilkan_rekap_dan_rincian(): void
    {
        Pengaturan::set('denda_per_hari', 1000);

        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-laporan-denda');
        $siswa = $this->buatUser('Siswa', 'siswa-laporan-denda');
        $idSiswa = $this->buatSiswa($siswa, '6101');

        $belum = $this->buatPeminjaman(
            $idSiswa,
            $this->buatBuku('Buku Denda Belum Lunas'),
            '2026-10-20',
            '2026-10-27',
            'Menunggu Pembayaran'
        );
        $this->buatPengembalian($belum, '2026-10-30', 3000, 'Belum Lunas');

        $lunas = $this->buatPeminjaman(
            $idSiswa,
            $this->buatBuku('Buku Denda Lunas'),
            '2026-10-21',
            '2026-10-27'
        );
        $this->buatPengembalian($lunas, '2026-10-29', 2000, 'Lunas');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/denda?periode=kustom&mulai=2026-10-01&selesai=2026-10-31')
            ->assertOk()
            ->assertSee('Laporan Denda')
            ->assertSee('Buku Denda Belum Lunas')
            ->assertSee('Buku Denda Lunas')
            ->assertSee('Rp 3.000')
            ->assertSee('Rp 2.000')
            ->assertSee('Rp 5.000')
            ->assertSee('3 hari')
            ->assertSee('2 hari')
            ->assertSee('Belum Lunas')
            ->assertSee('Lunas');
    }

    public function test_laporan_denda_bisa_difilter_status_kelas_dan_pencarian(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-filter-denda');
        $siswaA = $this->buatUser('Siswa', 'siswa-denda-a');
        $siswaB = $this->buatUser('Siswa', 'siswa-denda-b');
        $idA = $this->buatSiswa($siswaA, '6201', 'IX A');
        $idB = $this->buatSiswa($siswaB, '6202', 'IX B');

        $this->buatPengembalian(
            $this->buatPeminjaman($idA, $this->buatBuku('Buku Aljabar'), '2026-11-01', '2026-11-03', 'Menunggu Pembayaran'),
            '2026-11-05',
            2000,
            'Belum Lunas'
        );

        $this->buatPengembalian(
            $this->buatPeminjaman($idB, $this->buatBuku('Buku Biologi'), '2026-11-02', '2026-11-04'),
            '2026-11-06',
            1000,
            'Lunas'
        );

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/denda?periode=kustom&mulai=2026-11-01&selesai=2026-11-30&status=Belum+Lunas')
            ->assertOk()
            ->assertSee('Buku Aljabar')
            ->assertDontSee('Buku Biologi');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/denda?periode=kustom&mulai=2026-11-01&selesai=2026-11-30&kelas=IX+B')
            ->assertOk()
            ->assertSee('Buku Biologi')
            ->assertDontSee('Buku Aljabar');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/denda?periode=kustom&mulai=2026-11-01&selesai=2026-11-30&q=Aljabar')
            ->assertOk()
            ->assertSee('Buku Aljabar')
            ->assertDontSee('Buku Biologi');
    }

    public function test_pelunasan_denda_menyelesaikan_status_peminjaman(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-lunaskan');
        $siswa = $this->buatUser('Siswa', 'siswa-lunaskan');
        $idSiswa = $this->buatSiswa($siswa, '6301');

        $idPeminjaman = $this->buatPeminjaman(
            $idSiswa,
            $this->buatBuku('Buku Harus Dilunasi'),
            '2026-10-25',
            '2026-11-01',
            'Menunggu Pembayaran'
        );
        $idPengembalian = $this->buatPengembalian($idPeminjaman, '2026-11-04', 3000, 'Belum Lunas');

        $this->actingAs($pustakawan)
            ->patch("/pustakawan/denda/{$idPengembalian}/lunas")
            ->assertRedirect();

        $this->assertSame(
            'Lunas',
            DB::table('pengembalian')->where('id_pengembalian', $idPengembalian)->value('status_bayar')
        );
        $this->assertSame(
            'Dikembalikan',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );
    }

    public function test_pengembalian_tanpa_denda_langsung_lunas_dan_selesai(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-tanpa-denda');
        $siswa = $this->buatUser('Siswa', 'siswa-tanpa-denda');
        $idSiswa = $this->buatSiswa($siswa, '6401');

        $idPeminjaman = $this->buatPeminjaman(
            $idSiswa,
            $this->buatBuku('Buku Tepat Waktu'),
            '2026-11-01',
            '2026-11-30',
            'Dipinjam'
        );

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.pengembalian'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertRedirect(route('pustakawan.pengembalian'));

        $pengembalian = DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertNotNull($pengembalian);
        $this->assertSame('Lunas', $pengembalian->status_bayar);
        $this->assertSame(
            'Dikembalikan',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );
    }

    public function test_pdf_laporan_denda_memakai_ukuran_a4(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-pdf-denda');
        $siswa = $this->buatUser('Siswa', 'siswa-pdf-denda');
        $idSiswa = $this->buatSiswa($siswa, '6501');

        $this->buatPengembalian(
            $this->buatPeminjaman($idSiswa, $this->buatBuku('Buku PDF Denda'), '2026-11-01', '2026-11-03', 'Menunggu Pembayaran'),
            '2026-11-05',
            2000,
            'Belum Lunas'
        );

        $response = $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan/denda/pdf?periode=kustom&mulai=2026-11-01&selesai=2026-11-30');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=laporan-denda-20261101-20261130.pdf');

        $isi = $response->getContent();
        $this->assertStringStartsWith('%PDF', $isi);
        $this->assertGreaterThan(1000, strlen($isi));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RekapDataTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function buatKelas(string $nama): int
    {
        return DB::table('kelas')->insertGetId([
            'nama_kelas' => $nama,
            'tingkat' => explode(' ', $nama)[0],
            'wali_kelas' => null,
            'created_at' => now(),
        ]);
    }

    private function buatSiswa(int $idKelas, string $nama, string $nis): int
    {
        $user = User::factory()->create([
            'name' => $nama,
            'role' => 'Siswa',
        ]);

        return DB::table('siswa')->insertGetId([
            'id_user' => $user->id_user,
            'nis' => $nis,
            'nama_siswa' => $nama,
            'kelas' => DB::table('kelas')->where('id_kelas', $idKelas)->value('nama_kelas'),
            'id_kelas' => $idKelas,
        ]);
    }

    private function buatKategori(string $nama): int
    {
        return DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => $nama,
            'keterangan' => null,
        ]);
    }

    private function buatBuku(int $idKategori, string $judul): int
    {
        return DB::table('buku')->insertGetId([
            'id_kategori' => $idKategori,
            'no_seri_buku' => 'RKP-'.uniqid(),
            'judul' => $judul,
            'pengarang' => 'Penulis Uji',
            'penerbit' => 'Penerbit Uji',
            'tahun_terbit' => 2026,
            'kategori' => DB::table('kategori_buku')->where('id_kategori', $idKategori)->value('nama_kategori'),
            'lokasi_rak' => 'R1',
            'stok' => 10,
            'created_at' => now(),
        ]);
    }

    private function buatPeminjaman(int $idSiswa, string $tanggal, string $status): int
    {
        return DB::table('peminjaman')->insertGetId([
            'id_siswa' => $idSiswa,
            'tanggal_pinjam' => $tanggal,
            'tanggal_jatuh_tempo' => Carbon::parse($tanggal)->addDays(7)->toDateString(),
            'status' => $status,
        ]);
    }

    private function buatDetail(int $idPeminjaman, int $idBuku, int $jumlah): void
    {
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku' => $idBuku,
            'jumlah' => $jumlah,
        ]);
    }

    /**
     * Empat transaksi, delapan salinan buku, dua kelas, dua kategori.
     *
     * @return array<string, int>
     */
    private function siapkanData(): array
    {
        $kelasA = $this->buatKelas('VII A');
        $kelasB = $this->buatKelas('VIII B');

        $siswaA1 = $this->buatSiswa($kelasA, 'Aulia', '1001');
        $siswaA2 = $this->buatSiswa($kelasA, 'Budi', '1002');
        $siswaB = $this->buatSiswa($kelasB, 'Citra', '1003');

        $pelajaran = $this->buatKategori('Pelajaran');
        $cerita = $this->buatKategori('Cerita');

        $bukuMatematika = $this->buatBuku($pelajaran, 'Matematika VII');
        $bukuIpa = $this->buatBuku($pelajaran, 'IPA VII');
        $bukuCerita = $this->buatBuku($cerita, 'Kisah Nusantara');

        // Satu transaksi dengan tiga baris detail, dua di antaranya dari
        // kategori yang sama: transaksi tetap dihitung 1, salinan tetap 4.
        $p1 = $this->buatPeminjaman($siswaA1, '2026-10-01', 'Dipinjam');
        $this->buatDetail($p1, $bukuMatematika, 2);
        $this->buatDetail($p1, $bukuIpa, 1);
        $this->buatDetail($p1, $bukuCerita, 1);

        $p2 = $this->buatPeminjaman($siswaA2, '2026-10-05', 'Menunggu');
        $this->buatDetail($p2, $bukuIpa, 1);

        $p3 = $this->buatPeminjaman($siswaB, '2026-11-01', 'Dikembalikan');
        $this->buatDetail($p3, $bukuCerita, 2);

        $p4 = $this->buatPeminjaman($siswaA1, '2026-10-20', 'Ditolak');
        $this->buatDetail($p4, $bukuMatematika, 1);

        return compact('kelasA', 'kelasB', 'pelajaran', 'cerita');
    }

    public function test_admin_melihat_rekap_agregat_tanpa_menghitung_transaksi_ganda(): void
    {
        $this->siapkanData();

        $response = $this->actingAs($this->admin())->get('/admin/rekap');

        $response->assertOk()
            ->assertSee('Rekap Data')
            ->assertSee('VII A')
            ->assertSee('VIII B')
            ->assertSee('Pelajaran')
            ->assertSee('Cerita');

        $ringkasan = $response->viewData('ringkasan');

        $this->assertSame(4, $ringkasan['total_transaksi']);
        $this->assertSame(8, $ringkasan['total_buku']);
        $this->assertSame(2, $ringkasan['total_kelas']);
        $this->assertSame(2, $ringkasan['total_kategori']);

        $perKelas = $response->viewData('perKelas')->keyBy('label');

        $this->assertSame(3, $perKelas['VII A']['total_transaksi']);
        $this->assertSame(6, $perKelas['VII A']['total_buku']);
        $this->assertSame(1, $perKelas['VIII B']['total_transaksi']);
        $this->assertSame(2, $perKelas['VIII B']['total_buku']);

        $perKategori = $response->viewData('perKategori')->keyBy('label');

        $this->assertSame(3, $perKategori['Pelajaran']['total_transaksi']);
        $this->assertSame(5, $perKategori['Pelajaran']['total_buku']);
        $this->assertSame(2, $perKategori['Cerita']['total_transaksi']);
        $this->assertSame(3, $perKategori['Cerita']['total_buku']);

        $perStatus = $response->viewData('perStatus')->keyBy('label');

        $this->assertSame(1, $perStatus['Dipinjam']['total_transaksi']);
        $this->assertSame(4, $perStatus['Dipinjam']['total_buku']);
        $this->assertSame(1, $perStatus['Menunggu']['total_transaksi']);
        $this->assertSame(1, $perStatus['Dikembalikan']['total_transaksi']);
        $this->assertSame(1, $perStatus['Ditolak']['total_transaksi']);
    }

    public function test_filter_kelas_kategori_dan_status_mengubah_rekap(): void
    {
        $data = $this->siapkanData();
        $admin = $this->admin();

        $perKelas = $this->actingAs($admin)
            ->get('/admin/rekap?kelas='.$data['kelasA'])
            ->viewData('ringkasan');

        $this->assertSame(3, $perKelas['total_transaksi']);
        $this->assertSame(6, $perKelas['total_buku']);
        $this->assertSame(1, $perKelas['total_kelas']);

        $response = $this->get('/admin/rekap?kategori='.$data['cerita']);
        $perKategori = $response->viewData('ringkasan');

        $this->assertSame(2, $perKategori['total_transaksi']);
        $this->assertSame(3, $perKategori['total_buku']);
        $this->assertSame(2, $perKategori['total_kelas']);
        $this->assertSame(1, $perKategori['total_kategori']);
        $this->assertCount(1, $response->viewData('perKategori'));
        $this->assertSame('Cerita', $response->viewData('perKategori')->first()['label']);

        $perStatus = $this->get('/admin/rekap?status=Dipinjam')->viewData('ringkasan');

        $this->assertSame(1, $perStatus['total_transaksi']);
        $this->assertSame(4, $perStatus['total_buku']);
        $this->assertSame(1, $perStatus['total_kelas']);
        $this->assertSame(2, $perStatus['total_kategori']);
    }

    public function test_filter_periode_dan_kombinasi_filter_diterapkan_ke_semua_angka(): void
    {
        $data = $this->siapkanData();
        $admin = $this->admin();

        $perPeriode = $this->actingAs($admin)
            ->get('/admin/rekap?mulai=2026-10-01&selesai=2026-10-31')
            ->viewData('ringkasan');

        $this->assertSame(3, $perPeriode['total_transaksi']);
        $this->assertSame(6, $perPeriode['total_buku']);
        $this->assertSame(1, $perPeriode['total_kelas']);
        $this->assertSame(2, $perPeriode['total_kategori']);

        $response = $this->get('/admin/rekap?'.http_build_query([
            'kelas' => $data['kelasA'],
            'kategori' => $data['pelajaran'],
            'status' => 'Dipinjam',
            'mulai' => '2026-10-01',
            'selesai' => '2026-10-31',
        ]));

        $kombinasi = $response->viewData('ringkasan');

        $this->assertSame(1, $kombinasi['total_transaksi']);
        $this->assertSame(3, $kombinasi['total_buku']);
        $this->assertSame(1, $kombinasi['total_kelas']);
        $this->assertSame(1, $kombinasi['total_kategori']);
        $this->assertSame(1, $response->viewData('perKategori')->count());
        $this->assertSame(1, $response->viewData('perStatus')->count());
    }

    public function test_tanggal_terbalik_dan_tanggal_tidak_valid_tidak_merusak_halaman(): void
    {
        $this->siapkanData();
        $admin = $this->admin();

        // Tanggal terbalik ditukar otomatis, sehingga tetap ada hasil.
        $terbalik = $this->actingAs($admin)
            ->get('/admin/rekap?mulai=2026-10-31&selesai=2026-10-01')
            ->viewData('ringkasan');

        $this->assertSame(3, $terbalik['total_transaksi']);
        $this->assertSame(6, $terbalik['total_buku']);

        // Tanggal tidak valid diabaikan, halaman tetap terbuka.
        $tidakValid = $this->get('/admin/rekap?mulai=bukan-tanggal&selesai=2026-13-40');

        $tidakValid->assertOk();
        $this->assertSame(4, $tidakValid->viewData('ringkasan')['total_transaksi']);
    }

    public function test_rekap_hanya_bisa_dibuka_admin(): void
    {
        $pustakawan = User::factory()->create(['role' => 'Pustakawan']);
        $siswa = User::factory()->create(['role' => 'Siswa']);

        $this->actingAs($pustakawan)->get('/admin/rekap')->assertRedirect('/pustakawan');
        $this->actingAs($siswa)->get('/admin/rekap')->assertRedirect('/dashboard');
    }
}

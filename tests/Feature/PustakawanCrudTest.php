<?php

namespace Tests\Feature;

use App\Models\TUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PustakawanCrudTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username, string $password = 'secret'): TUser
    {
        $id = DB::table('t_user')->insertGetId([
            'username'   => $username,
            'name'       => ucfirst($username),
            'password'   => Hash::make($password),
            'role'       => $role,
            'created_at' => now(),
        ]);

        return TUser::findOrFail($id);
    }

    private function buatSiswa(TUser $user, string $nis, string $nama = null, string $kelas = 'VII A', ?int $idKelas = null): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => $nama ?? ('Siswa ' . $nis),
            'kelas'      => $kelas,
            'id_kelas'   => $idKelas,
        ]);
    }

    private function buatKategori(string $nama = 'Pelajaran'): int
    {
        return DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => $nama,
            'keterangan'    => 'Kategori uji',
        ]);
    }

    private function buatBuku(int $idKategori, string $judul = 'Buku Uji', string $seri = null): int
    {
        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => $seri ?? ('UJI-' . uniqid()),
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

    private function buatPeminjaman(int $idSiswa, int $idBuku, string $status = 'Dikembalikan'): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'             => $idSiswa,
            'tanggal_pinjam'       => now()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo'  => now()->subDays(3)->toDateString(),
            'status'               => $status,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    public function test_pustakawan_bisa_mengakses_tiga_menu_baru(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-crud');
        $admin = $this->buatUser('Admin', 'admin-crud');

        foreach (['/pustakawan/buku', '/pustakawan/anggota', '/pustakawan/kelas'] as $url) {
            $this->actingAs($pustakawan)->get($url)->assertOk();
        }

        $this->actingAs($pustakawan)->get('/pustakawan/buku/create')->assertOk();
        $this->actingAs($pustakawan)->get('/pustakawan/anggota/create')->assertOk();
        $this->actingAs($pustakawan)->get('/pustakawan/kelas/create')->assertOk();

        // Menu sidebar Pustakawan sesuai pembagian tugas; Data Kelas tidak lagi ditampilkan.
        $this->actingAs($pustakawan)->get('/pustakawan')
            ->assertOk()
            ->assertSee('Data Buku')
            ->assertSee('Data Anggota')
            ->assertDontSee('Data Kelas');

        // Admin dan Siswa tidak boleh masuk area Pustakawan.
        $this->actingAs($admin)->get('/pustakawan/buku')->assertRedirect('/admin');

        $siswa = $this->buatUser('Siswa', 'siswa-crud');
        $this->actingAs($siswa)->get('/pustakawan/anggota')->assertRedirect('/dashboard');
    }

    public function test_pustakawan_mengelola_data_buku(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-buku');
        $idKategori = $this->buatKategori();

        $this->actingAs($pustakawan)->get('/pustakawan/buku/create')
            ->assertOk()
            ->assertSee('Tambah Buku');

        $this->actingAs($pustakawan)->post('/pustakawan/buku', [
            'no_seri_buku' => 'BK-001',
            'judul'        => 'Matematika Kelas VII',
            'pengarang'    => 'Tim Penulis',
            'penerbit'     => 'Kemendikbud',
            'id_kategori'  => $idKategori,
            'tahun_terbit' => 2026,
            'lokasi_rak'   => 'R-01',
            'stok'         => 4,
        ])->assertRedirect(route('pustakawan.buku'));

        $buku = DB::table('buku')->where('no_seri_buku', 'BK-001')->first();
        $this->assertNotNull($buku);
        $this->assertSame('Matematika Kelas VII', $buku->judul);

        // Detail buku dapat dibuka dari area Pustakawan.
        $this->actingAs($pustakawan)->get("/pustakawan/buku/{$buku->id_buku}")
            ->assertOk()
            ->assertSee('Detail Buku')
            ->assertSee('Matematika Kelas VII');

        $this->actingAs($pustakawan)->get("/pustakawan/buku/{$buku->id_buku}/edit")
            ->assertOk()
            ->assertSee('Edit Buku');

        $this->actingAs($pustakawan)->put("/pustakawan/buku/{$buku->id_buku}", [
            'no_seri_buku' => 'BK-001',
            'judul'        => 'Matematika Kelas VII Revisi',
            'pengarang'    => 'Tim Penulis',
            'penerbit'     => 'Kemendikbud',
            'id_kategori'  => $idKategori,
            'tahun_terbit' => 2026,
            'lokasi_rak'   => 'R-02',
            'stok'         => 6,
        ])->assertRedirect(route('pustakawan.buku'));

        $this->assertDatabaseHas('buku', [
            'id_buku' => $buku->id_buku,
            'judul'   => 'Matematika Kelas VII Revisi',
            'stok'    => 6,
        ]);

        // Buku tanpa riwayat boleh dihapus.
        $this->actingAs($pustakawan)->delete("/pustakawan/buku/{$buku->id_buku}")
            ->assertRedirect(route('pustakawan.buku'));

        $this->assertDatabaseMissing('buku', ['id_buku' => $buku->id_buku]);

        // Buku yang sudah punya riwayat peminjaman tidak boleh dihapus.
        $idBukuTerpakai = $this->buatBuku($idKategori, 'Buku Terpakai', 'BK-002');
        $siswaUser = $this->buatUser('Siswa', 'siswa-buku');
        $idSiswa = $this->buatSiswa($siswaUser, '2001');
        $this->buatPeminjaman($idSiswa, $idBukuTerpakai);

        $this->actingAs($pustakawan)->delete("/pustakawan/buku/{$idBukuTerpakai}")
            ->assertRedirect(route('pustakawan.buku'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('buku', ['id_buku' => $idBukuTerpakai]);
        $this->assertDatabaseHas('detail_peminjaman', ['id_buku' => $idBukuTerpakai]);
    }

    public function test_pustakawan_mengelola_data_anggota(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-anggota');
        $idKelas = DB::table('kelas')->insertGetId([
            'nama_kelas' => 'VII A',
            'tingkat'    => 'VII',
            'wali_kelas' => 'Ibu Guru',
            'created_at' => now(),
        ]);

        $this->actingAs($pustakawan)->post('/pustakawan/anggota', [
            'username'      => '3001@siswa.sipus.sch.id',
            'password'      => 'rahasia123',
            'nis'           => '3001',
            'nama_siswa'    => 'Anggota Baru',
            'kelas'         => 'VII A',
            'jenis_kelamin' => 'Perempuan',
        ])->assertRedirect(route('pustakawan.anggota'));

        $siswa = DB::table('siswa')->where('nis', '3001')->first();
        $this->assertNotNull($siswa);
        $this->assertSame('Anggota Baru', $siswa->nama_siswa);
        $this->assertSame($idKelas, $siswa->id_kelas);
        $this->assertDatabaseHas('t_user', [
            'id_user'  => $siswa->id_user,
            'username' => '3001@siswa.sipus.sch.id',
            'role'     => 'Siswa',
        ]);

        $this->actingAs($pustakawan)->get("/pustakawan/anggota/{$siswa->id_siswa}")
            ->assertOk()
            ->assertSee('Anggota Baru')
            ->assertSee('3001');

        $this->actingAs($pustakawan)->get("/pustakawan/anggota/{$siswa->id_siswa}/edit")
            ->assertOk()
            ->assertSee('Edit Anggota');

        $this->actingAs($pustakawan)->put("/pustakawan/anggota/{$siswa->id_siswa}", [
            'username'      => '3001@siswa.sipus.sch.id',
            'nis'           => '3001',
            'nama_siswa'    => 'Anggota Diperbarui',
            'kelas'         => 'VII A',
            'jenis_kelamin' => 'Perempuan',
        ])->assertRedirect(route('pustakawan.anggota'));

        $this->assertDatabaseHas('siswa', [
            'id_siswa'   => $siswa->id_siswa,
            'nama_siswa' => 'Anggota Diperbarui',
        ]);

        // Anggota tanpa riwayat peminjaman boleh dihapus beserta akunnya.
        $this->actingAs($pustakawan)->delete("/pustakawan/anggota/{$siswa->id_siswa}")
            ->assertRedirect(route('pustakawan.anggota'));

        $this->assertDatabaseMissing('siswa', ['id_siswa' => $siswa->id_siswa]);
        $this->assertDatabaseMissing('t_user', ['id_user' => $siswa->id_user]);
    }

    public function test_anggota_dengan_riwayat_peminjaman_tidak_boleh_dihapus(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-riwayat');
        $siswaUser = $this->buatUser('Siswa', 'siswa-riwayat');
        $idSiswa = $this->buatSiswa($siswaUser, '4001');
        $idKategori = $this->buatKategori();
        $idBuku = $this->buatBuku($idKategori);
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, 'Dikembalikan');

        $this->actingAs($pustakawan)->delete("/pustakawan/anggota/{$idSiswa}")
            ->assertRedirect(route('pustakawan.anggota'))
            ->assertSessionHas('error');

        // Semua data lama harus tetap utuh.
        $this->assertDatabaseHas('siswa', ['id_siswa' => $idSiswa]);
        $this->assertDatabaseHas('t_user', ['id_user' => $siswaUser->id_user]);
        $this->assertDatabaseHas('peminjaman', ['id_peminjaman' => $idPeminjaman]);
        $this->assertDatabaseHas('detail_peminjaman', ['id_peminjaman' => $idPeminjaman]);
    }

    public function test_pustakawan_mengelola_kelas_dan_menolak_nama_duplikat(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-kelas');

        $this->actingAs($pustakawan)->post('/pustakawan/kelas', [
            'nama_kelas' => 'VIII A',
            'tingkat'    => 'VIII',
            'wali_kelas' => 'Pak Guru',
        ])->assertRedirect(route('pustakawan.kelas'));

        $kelas = DB::table('kelas')->where('nama_kelas', 'VIII A')->first();
        $this->assertNotNull($kelas);
        $this->assertSame('Pak Guru', $kelas->wali_kelas);

        // Duplikat dengan spasi dan kapitalisasi berbeda harus ditolak.
        $this->actingAs($pustakawan)->post('/pustakawan/kelas', [
            'nama_kelas' => '  viii a  ',
            'tingkat'    => 'VIII',
        ])->assertSessionHasErrors('nama_kelas');

        $this->assertSame(1, DB::table('kelas')->whereRaw('LOWER(TRIM(nama_kelas)) = ?', ['viii a'])->count());

        // Nama kelas yang dipakai siswa ikut tersinkron saat diedit.
        $siswaUser = $this->buatUser('Siswa', 'siswa-kelas');
        $idSiswa = $this->buatSiswa($siswaUser, '5001', 'Siswa Kelas', 'VIII A', $kelas->id_kelas);

        $this->actingAs($pustakawan)->get("/pustakawan/kelas/{$kelas->id_kelas}/edit")
            ->assertOk()
            ->assertSee('Edit Kelas');

        $this->actingAs($pustakawan)->put("/pustakawan/kelas/{$kelas->id_kelas}", [
            'nama_kelas' => 'VIII B',
            'tingkat'    => 'VIII',
            'wali_kelas' => 'Bu Guru',
        ])->assertRedirect(route('pustakawan.kelas'));

        $this->assertDatabaseHas('kelas', [
            'id_kelas'   => $kelas->id_kelas,
            'nama_kelas' => 'VIII B',
        ]);
        $this->assertDatabaseHas('siswa', [
            'id_siswa' => $idSiswa,
            'kelas'    => 'VIII B',
        ]);

        // Kelas yang masih memiliki siswa tidak boleh dihapus.
        $this->actingAs($pustakawan)->delete("/pustakawan/kelas/{$kelas->id_kelas}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('kelas', ['id_kelas' => $kelas->id_kelas]);

        // Setelah siswa dihapus, kelas kosong boleh dihapus.
        DB::table('siswa')->where('id_siswa', $idSiswa)->delete();

        $this->actingAs($pustakawan)->delete("/pustakawan/kelas/{$kelas->id_kelas}")
            ->assertRedirect(route('pustakawan.kelas'));

        $this->assertDatabaseMissing('kelas', ['id_kelas' => $kelas->id_kelas]);
    }
}

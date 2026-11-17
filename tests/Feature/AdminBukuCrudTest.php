<?php

namespace Tests\Feature;

use App\Models\TUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBukuCrudTest extends TestCase
{
    use RefreshDatabase;

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

    private function buatKategori(string $nama): int
    {
        return DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => $nama,
            'keterangan'    => 'Kategori uji',
        ]);
    }

    private function buatBuku(int $idKategori, string $judul, string $seri, int $stok = 5): int
    {
        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => $seri,
            'judul'        => $judul,
            'pengarang'    => 'Penulis Uji',
            'penerbit'     => 'Penerbit Uji',
            'tahun_terbit' => 2026,
            'kategori'     => 'Pelajaran',
            'lokasi_rak'   => 'R1',
            'stok'         => $stok,
            'created_at'   => now(),
        ]);
    }

    private function buatSiswa(string $nis = '7001'): int
    {
        $user = $this->buatUser('Siswa', 'siswa-' . $nis);

        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa ' . $nis,
            'kelas'      => 'VII A',
        ]);
    }

    private function buatPeminjaman(int $idSiswa, int $idBuku, string $status): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => now()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => now()->addDays(4)->toDateString(),
            'status'              => $status,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    public function test_halaman_kelola_buku_menampilkan_tombol_edit_dan_hapus(): void
    {
        $admin = $this->buatUser('Admin', 'admin-tombol');
        $idKategori = $this->buatKategori('Pelajaran');
        $idBuku = $this->buatBuku($idKategori, 'Buku Tanpa Riwayat', 'TBL-001');

        $this->actingAs($admin)->get('/admin/buku')
            ->assertOk()
            ->assertSee(route('admin.buku.edit', $idBuku), false)
            ->assertSee(route('admin.buku.destroy', $idBuku), false)
            ->assertSee('Edit buku')
            ->assertSee('Hapus buku')
            ->assertSee('return confirm(', false);
    }

    public function test_admin_bisa_mengubah_buku_dan_perubahan_tersimpan(): void
    {
        $admin = $this->buatUser('Admin', 'admin-edit');
        $idKategori = $this->buatKategori('Pelajaran');
        $idKategoriBaru = $this->buatKategori('Cerita');
        $idBuku = $this->buatBuku($idKategori, 'Judul Awal', 'EDT-001', 3);

        $this->actingAs($admin)->get("/admin/buku/{$idBuku}/edit")
            ->assertOk()
            ->assertSee('Edit Buku')
            ->assertSee('Judul Awal');

        $this->actingAs($admin)->put("/admin/buku/{$idBuku}", [
            'no_seri_buku' => 'EDT-002',
            'judul'        => 'Judul Setelah Edit',
            'pengarang'    => 'Pengarang Baru',
            'penerbit'     => 'Penerbit Baru',
            'id_kategori'  => $idKategoriBaru,
            'tahun_terbit' => 2025,
            'lokasi_rak'   => 'R-09',
            'stok'         => 8,
        ])->assertRedirect(route('admin.buku'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('buku', [
            'id_buku'      => $idBuku,
            'no_seri_buku' => 'EDT-002',
            'judul'        => 'Judul Setelah Edit',
            'pengarang'    => 'Pengarang Baru',
            'penerbit'     => 'Penerbit Baru',
            'id_kategori'  => $idKategoriBaru,
            'kategori'     => 'Cerita',
            'tahun_terbit' => 2025,
            'lokasi_rak'   => 'R-09',
            'stok'         => 8,
        ]);
    }

    public function test_admin_bisa_menghapus_buku_tanpa_transaksi(): void
    {
        $admin = $this->buatUser('Admin', 'admin-hapus');
        $idKategori = $this->buatKategori('Pelajaran');
        $idBuku = $this->buatBuku($idKategori, 'Buku Akan Dihapus', 'DEL-001');

        $this->actingAs($admin)->delete("/admin/buku/{$idBuku}")
            ->assertRedirect(route('admin.buku'))
            ->assertSessionHas('success', 'Buku berhasil dihapus.');

        $this->assertDatabaseMissing('buku', ['id_buku' => $idBuku]);
    }

    public function test_buku_dengan_peminjaman_aktif_tidak_boleh_dihapus(): void
    {
        $admin = $this->buatUser('Admin', 'admin-aktif');
        $idKategori = $this->buatKategori('Pelajaran');
        $idBuku = $this->buatBuku($idKategori, 'Buku Sedang Dipinjam', 'ACT-001');
        $idSiswa = $this->buatSiswa('7001');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, 'Dipinjam');

        $this->actingAs($admin)->delete("/admin/buku/{$idBuku}")
            ->assertRedirect(route('admin.buku'))
            ->assertSessionHas('error', 'Buku tidak dapat dihapus karena masih memiliki transaksi peminjaman aktif.');

        $this->assertDatabaseHas('buku', ['id_buku' => $idBuku]);
        $this->assertDatabaseHas('peminjaman', ['id_peminjaman' => $idPeminjaman]);
        $this->assertDatabaseHas('detail_peminjaman', [
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
        ]);
    }

    public function test_buku_dengan_riwayat_peminjaman_tetap_dilindungi(): void
    {
        $admin = $this->buatUser('Admin', 'admin-riwayat');
        $idKategori = $this->buatKategori('Pelajaran');
        $idBuku = $this->buatBuku($idKategori, 'Buku Sudah Dikembalikan', 'HIS-001');
        $idSiswa = $this->buatSiswa('7002');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, 'Dikembalikan');

        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => now()->toDateString(),
            'denda'           => 0,
            'kategori'        => 'Tepat Waktu',
        ]);

        $this->actingAs($admin)->delete("/admin/buku/{$idBuku}")
            ->assertRedirect(route('admin.buku'))
            ->assertSessionHas('error', 'Buku tidak dapat dihapus karena memiliki riwayat peminjaman. Riwayat transaksi harus tetap tersimpan.');

        $this->assertDatabaseHas('buku', ['id_buku' => $idBuku]);
        $this->assertDatabaseHas('detail_peminjaman', ['id_buku' => $idBuku]);
        $this->assertDatabaseHas('pengembalian', ['id_peminjaman' => $idPeminjaman]);
    }
}

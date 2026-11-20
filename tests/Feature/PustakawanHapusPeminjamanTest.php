<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PustakawanHapusPeminjamanTest extends TestCase
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

    private function buatSiswa(TUser $user, string $nis = '4001'): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa ' . $nis,
            'kelas'      => 'VIII A',
        ]);
    }

    private function buatBuku(int $stok, string $judul): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'HAPUS-' . uniqid(),
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

    private function buatPeminjaman(
        int $idSiswa,
        int $idBuku,
        string $status = 'Dipinjam',
        string $tanggalPinjam = '2026-11-01',
        int $jumlah = 1
    ): int {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => $tanggalPinjam,
            'tanggal_jatuh_tempo' => Carbon::parse($tanggalPinjam)->addDays(7)->toDateString(),
            'status'              => $status,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => $jumlah,
        ]);

        return $idPeminjaman;
    }

    public function test_pustakawan_menghapus_transaksi_aktif_dan_mengembalikan_stok(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-hapus');
        $siswaUser = $this->buatUser('Siswa', 'siswa-hapus');
        $idSiswa = $this->buatSiswa($siswaUser);
        $idBuku = $this->buatBuku(2, 'Buku Salah Input');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.peminjaman'))
            ->delete("/pustakawan/peminjaman/{$idPeminjaman}")
            ->assertRedirect(route('pustakawan.peminjaman'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('peminjaman', ['id_peminjaman' => $idPeminjaman]);
        $this->assertDatabaseMissing('detail_peminjaman', ['id_peminjaman' => $idPeminjaman]);

        // Stok kembali ke jumlah sebelum transaksi dibuat.
        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_menghapus_pengajuan_menunggu_tidak_mengubah_stok(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-hapus-menunggu');
        $siswaUser = $this->buatUser('Siswa', 'siswa-hapus-menunggu');
        $idSiswa = $this->buatSiswa($siswaUser, '4002');
        $idBuku = $this->buatBuku(3, 'Buku Pengajuan Salah');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, 'Menunggu');

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.pengajuan'))
            ->delete("/pustakawan/peminjaman/{$idPeminjaman}")
            ->assertRedirect(route('pustakawan.peminjaman'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('peminjaman', ['id_peminjaman' => $idPeminjaman]);

        // Pengajuan belum mengurangi stok, jadi stok tidak boleh bertambah.
        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_transaksi_yang_sudah_dikembalikan_tidak_bisa_dihapus(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-hapus-riwayat');
        $siswaUser = $this->buatUser('Siswa', 'siswa-hapus-riwayat');
        $idSiswa = $this->buatSiswa($siswaUser, '4003');
        $idBuku = $this->buatBuku(3, 'Buku Sudah Kembali');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, 'Dikembalikan');

        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => '2026-11-08',
            'denda'           => 0,
            'kategori'        => 'Tepat Waktu',
            'status_bayar'    => 'Lunas',
        ]);

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.peminjaman', ['tab' => 'riwayat']))
            ->delete("/pustakawan/peminjaman/{$idPeminjaman}")
            ->assertRedirect(route('pustakawan.peminjaman'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('peminjaman', ['id_peminjaman' => $idPeminjaman]);
        $this->assertDatabaseHas('pengembalian', ['id_peminjaman' => $idPeminjaman]);
        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_tombol_hapus_hanya_tampil_untuk_transaksi_aktif(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-tombol-hapus');
        $siswaUser = $this->buatUser('Siswa', 'siswa-tombol-hapus');
        $idSiswa = $this->buatSiswa($siswaUser, '4004');
        $idBuku = $this->buatBuku(2, 'Buku Tombol Hapus');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman')
            ->assertOk()
            ->assertSee('Hapus transaksi salah input')
            ->assertSee(route('pustakawan.peminjaman.destroy', $idPeminjaman), false);

        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => '2026-11-08',
            'denda'           => 0,
            'kategori'        => 'Tepat Waktu',
            'status_bayar'    => 'Lunas',
        ]);

        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman?tab=riwayat')
            ->assertOk()
            ->assertDontSee('Hapus transaksi salah input');
    }

    public function test_role_lain_tidak_bisa_menghapus_peminjaman(): void
    {
        $admin = $this->buatUser('Admin', 'admin-hapus');
        $siswaUser = $this->buatUser('Siswa', 'siswa-hapus-role');
        $idSiswa = $this->buatSiswa($siswaUser, '4005');
        $idBuku = $this->buatBuku(2, 'Buku Role Lain');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($admin)
            ->delete("/pustakawan/peminjaman/{$idPeminjaman}")
            ->assertRedirect('/admin');

        $this->actingAs($siswaUser)
            ->delete("/pustakawan/peminjaman/{$idPeminjaman}")
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('peminjaman', ['id_peminjaman' => $idPeminjaman]);
        $this->assertSame(2, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_laporan_semua_menampilkan_seluruh_bulan_dan_tahun(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan-laporan-semua');
        $siswaUser = $this->buatUser('Siswa', 'siswa-laporan-semua');
        $idSiswa = $this->buatSiswa($siswaUser, '4006');
        $idBukuLama = $this->buatBuku(2, 'Buku Tahun 2025');
        $idBukuBaru = $this->buatBuku(2, 'Buku Tahun 2026');

        $this->buatPeminjaman($idSiswa, $idBukuLama, 'Dipinjam', '2025-12-25');
        $this->buatPeminjaman($idSiswa, $idBukuBaru, 'Dipinjam', '2026-11-11');

        // Tanpa parameter periode, laporan memakai seluruh riwayat.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan')
            ->assertOk()
            ->assertSee('Buku Tahun 2025')
            ->assertSee('Buku Tahun 2026')
            ->assertViewHas('kolomJudul', function (array $kolom): bool {
                $tahun = collect($kolom)->pluck('tahun')->all();

                return in_array('2025', $tahun, true) && in_array('2026', $tahun, true);
            });

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan?periode=semua')
            ->assertOk()
            ->assertSee('Buku Tahun 2025')
            ->assertSee('Buku Tahun 2026');
    }
}

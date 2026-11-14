<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PustakawanRiwayatTransaksiTest extends TestCase
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
            'kelas'      => 'IX A',
        ]);
    }

    private function buatBuku(string $judul): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji riwayat',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'RIW-' . uniqid(),
            'judul'        => $judul,
            'pengarang'    => 'Penulis Uji',
            'penerbit'     => 'Penerbit Uji',
            'tahun_terbit' => 2026,
            'kategori'     => 'Pelajaran',
            'lokasi_rak'   => 'R1',
            'stok'         => 1,
            'created_at'   => now(),
        ]);
    }

    private function buatPeminjaman(int $idSiswa, int $idBuku, string $status, Carbon $pinjam, Carbon $jatuhTempo): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => $pinjam->toDateString(),
            'tanggal_jatuh_tempo' => $jatuhTempo->toDateString(),
            'status'              => $status,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    private function buatPengembalian(int $idPeminjaman, string $kategori, float $denda, Carbon $tanggalKembali): void
    {
        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => $tanggalKembali->toDateString(),
            'denda'           => $denda,
            'kategori'        => $kategori,
            'status_bayar'    => $denda > 0 ? 'Belum Lunas' : 'Lunas',
        ]);
    }

    /**
     * @return array{0: TUser, 1: int, 2: int, 3: int}
     */
    private function siapkanData(): array
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanriwayat');
        $siswaUser = $this->buatUser('Siswa', 'siswariwayat');
        $idSiswa = $this->buatSiswa($siswaUser);

        $bukuAktif = $this->buatBuku('Buku Transaksi Aktif');
        $bukuRiwayat = $this->buatBuku('Buku Riwayat Terlambat');
        $bukuTepat = $this->buatBuku('Buku Riwayat Tepat Waktu');

        $idAktif = $this->buatPeminjaman(
            $idSiswa,
            $bukuAktif,
            'Dipinjam',
            Carbon::today()->subDays(2),
            Carbon::today()->addDays(5)
        );

        $idRiwayat = $this->buatPeminjaman(
            $idSiswa,
            $bukuRiwayat,
            'Dikembalikan',
            Carbon::today()->subDays(20),
            Carbon::today()->subDays(10)
        );
        $this->buatPengembalian($idRiwayat, 'Terlambat', 5000, Carbon::today()->subDays(5));

        $idTepat = $this->buatPeminjaman(
            $idSiswa,
            $bukuTepat,
            'Dikembalikan',
            Carbon::today()->subDays(20),
            Carbon::today()->subDays(2)
        );
        $this->buatPengembalian($idTepat, 'Tepat Waktu', 0, Carbon::today()->subDays(3));

        return [$pustakawan, $idAktif, $idRiwayat, $idTepat];
    }

    public function test_tab_riwayat_menampilkan_transaksi_yang_sudah_dikembalikan(): void
    {
        [$pustakawan] = $this->siapkanData();

        $response = $this->actingAs($pustakawan)->get('/pustakawan/peminjaman?tab=riwayat');

        $response->assertOk()
            ->assertSee('Riwayat Transaksi')
            ->assertSee('Buku Riwayat Terlambat')
            ->assertSee('Buku Riwayat Tepat Waktu')
            ->assertSee('Rp 5.000')
            ->assertSee('Terlambat')
            ->assertDontSee('Buku Transaksi Aktif');
    }

    public function test_tab_aktif_tidak_menampilkan_transaksi_yang_sudah_dikembalikan(): void
    {
        [$pustakawan] = $this->siapkanData();

        $response = $this->actingAs($pustakawan)->get('/pustakawan/peminjaman?tab=aktif');

        $response->assertOk()
            ->assertSee('Transaksi Aktif')
            ->assertSee('Buku Transaksi Aktif')
            ->assertDontSee('Buku Riwayat Terlambat')
            ->assertDontSee('Buku Riwayat Tepat Waktu');
    }

    public function test_filter_riwayat_membedakan_tepat_waktu_dan_terlambat(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman?tab=riwayat&status=terlambat')
            ->assertOk()
            ->assertSee('Buku Riwayat Terlambat')
            ->assertDontSee('Buku Riwayat Tepat Waktu');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman?tab=riwayat&status=tepat-waktu')
            ->assertOk()
            ->assertSee('Buku Riwayat Tepat Waktu')
            ->assertDontSee('Buku Riwayat Terlambat');
    }

    public function test_tautan_lama_status_dikembalikan_tetap_menampilkan_riwayat(): void
    {
        [$pustakawan] = $this->siapkanData();

        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman?status=dikembalikan')
            ->assertOk()
            ->assertSee('Buku Riwayat Terlambat')
            ->assertSee('Buku Riwayat Tepat Waktu')
            ->assertDontSee('Buku Transaksi Aktif');
    }
}

<?php

namespace Tests\Feature;

use App\Models\TUser;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PustakawanPengembalianSatuKlikTest extends TestCase
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

    private function buatSiswa(TUser $user, string $nis = '3001'): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa ' . $nis,
            'kelas'      => 'VIII A',
        ]);
    }

    private function buatBuku(int $stok = 3, string $judul = 'Buku Satu Klik'): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'KLIK-' . uniqid(),
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

    private function buatPeminjaman(int $idSiswa, int $idBuku, ?Carbon $jatuhTempo = null, ?Carbon $diajukan = null): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'             => $idSiswa,
            'tanggal_pinjam'       => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo'  => ($jatuhTempo ?? Carbon::today()->addDays(4))->toDateString(),
            'status'               => 'Dipinjam',
            'pengajuan_kembali_at' => $diajukan,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    public function test_pustakawan_mencatat_pengembalian_dengan_satu_tombol(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan1klik');
        $siswaUser = $this->buatUser('Siswa', 'siswa1klik');
        $idSiswa = $this->buatSiswa($siswaUser);
        $idBuku = $this->buatBuku(3, 'Buku Tepat Waktu');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, Carbon::today());

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.peminjaman'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertRedirect(route('pustakawan.peminjaman'))
            ->assertSessionHas('success');

        $this->assertSame(
            'Dikembalikan',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );

        $pengembalian = DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertNotNull($pengembalian);
        $this->assertSame(Carbon::today()->toDateString(), $pengembalian->tanggal_kembali);
        $this->assertSame('Tepat Waktu', $pengembalian->kategori);
        $this->assertEquals(0, (float) $pengembalian->denda);

        // Stok buku kembali bertambah satu.
        $this->assertSame(4, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_pengembalian_satu_klik_menghitung_denda_keterlambatan(): void
    {
        Pengaturan::set('denda_per_hari', 750);

        $pustakawan = $this->buatUser('Pustakawan', 'pustakawandenda');
        $siswaUser = $this->buatUser('Siswa', 'siswadenda');
        $idSiswa = $this->buatSiswa($siswaUser, '3002');
        $idBuku = $this->buatBuku(2, 'Buku Terlambat');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, Carbon::today()->subDays(3));

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.pengembalian'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertRedirect(route('pustakawan.pengembalian'))
            ->assertSessionHas('success');

        $pengembalian = DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertSame('Terlambat', $pengembalian->kategori);
        $this->assertEquals(2250, (float) $pengembalian->denda);
        $this->assertSame('Belum Lunas', $pengembalian->status_bayar);
        $this->assertSame('Menunggu Pembayaran', DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status'));
    }

    public function test_pengembalian_satu_klik_menolak_pencatatan_ganda(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanganda');
        $siswaUser = $this->buatUser('Siswa', 'siswaganda');
        $idSiswa = $this->buatSiswa($siswaUser, '3003');
        $idBuku = $this->buatBuku(3, 'Buku Ganda');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.pengembalian'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertSessionHas('success');

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.pengembalian'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertSessionHas('info');

        $this->assertSame(
            1,
            DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->count()
        );

        // Stok hanya bertambah sekali.
        $this->assertSame(4, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_pengembalian_satu_klik_memproses_pengajuan_siswa(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanajuan');
        $siswaUser = $this->buatUser('Siswa', 'siswaajuan');
        $idSiswa = $this->buatSiswa($siswaUser, '3004');
        $idBuku = $this->buatBuku(3, 'Buku Diajukan');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, null, Carbon::now());

        // Antrean di halaman pengembalian menampilkan pengajuan siswa
        // beserta tombol satu-klik.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian')
            ->assertOk()
            ->assertSee('Buku Diajukan')
            ->assertSee('Menunggu Pengembalian')
            ->assertSee(route('pustakawan.peminjaman.kembalikan', $idPeminjaman), false);

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.pengembalian'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertRedirect(route('pustakawan.pengembalian'));

        $this->assertSame(
            'Dikembalikan',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );

        // Siswa melihat status akhir Dikembalikan di riwayatnya.
        $this->actingAs($siswaUser)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee('Buku Diajukan')
            ->assertSee('Dikembalikan');
    }

    public function test_hanya_pustakawan_yang_bisa_memakai_tombol_pengembalian(): void
    {
        $admin = $this->buatUser('Admin', 'adminroleklik');
        $siswaUser = $this->buatUser('Siswa', 'siswaroleklik');
        $idSiswa = $this->buatSiswa($siswaUser, '3005');
        $idBuku = $this->buatBuku(3, 'Buku Role Klik');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($siswaUser)
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertRedirect('/dashboard');

        $this->actingAs($admin)
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/kembalikan")
            ->assertRedirect('/admin');

        $this->assertSame(
            0,
            DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->count()
        );
        $this->assertSame(
            'Dipinjam',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );
    }

    public function test_aksi_kembalikan_tidak_tampil_di_menu_transaksi_peminjaman(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawantombol');
        $siswaUser = $this->buatUser('Siswa', 'siswatombol');
        $idSiswa = $this->buatSiswa($siswaUser, '3006');

        $idBukuAktif = $this->buatBuku(3, 'Buku Masih Dipinjam');
        $idPeminjamanAktif = $this->buatPeminjaman($idSiswa, $idBukuAktif, Carbon::today()->subDays(1));

        $idBukuSelesai = $this->buatBuku(3, 'Buku Sudah Kembali');
        $idPeminjamanSelesai = $this->buatPeminjaman($idSiswa, $idBukuSelesai);
        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjamanSelesai,
            'tanggal_kembali' => Carbon::today()->toDateString(),
            'denda'           => 0,
            'kategori'        => 'Tepat Waktu',
        ]);
        DB::table('peminjaman')->where('id_peminjaman', $idPeminjamanSelesai)->update(['status' => 'Dikembalikan']);

        // Transaksi Peminjaman tidak lagi memuat aksi pengembalian buku.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman')
            ->assertOk()
            ->assertDontSee(route('pustakawan.peminjaman.kembalikan', $idPeminjamanAktif), false)
            ->assertDontSee(route('pustakawan.peminjaman.kembalikan', $idPeminjamanSelesai), false);

        $this->actingAs($pustakawan)
            ->get("/pustakawan/peminjaman/{$idPeminjamanAktif}")
            ->assertOk()
            ->assertDontSee('Kembalikan Buku');

        // Pencatatan pengembalian hanya tersedia di menu Pengembalian.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian')
            ->assertOk()
            ->assertSee(route('pustakawan.peminjaman.kembalikan', $idPeminjamanAktif), false);
    }

    public function test_menu_pengembalian_memiliki_tab_peminjaman_aktif_dan_riwayat(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawantab');
        $siswaUser = $this->buatUser('Siswa', 'siswatab');
        $idSiswa = $this->buatSiswa($siswaUser, '3007');

        $idBukuAktif = $this->buatBuku(3, 'Buku Tab Aktif');
        $idPeminjamanAktif = $this->buatPeminjaman($idSiswa, $idBukuAktif);

        $idBukuSelesai = $this->buatBuku(3, 'Buku Tab Riwayat');
        $idPeminjamanSelesai = $this->buatPeminjaman($idSiswa, $idBukuSelesai);
        DB::table('pengembalian')->insert([
            'id_peminjaman'   => $idPeminjamanSelesai,
            'tanggal_kembali' => Carbon::today()->toDateString(),
            'denda'           => 0,
            'kategori'        => 'Tepat Waktu',
        ]);
        DB::table('peminjaman')->where('id_peminjaman', $idPeminjamanSelesai)->update(['status' => 'Dikembalikan']);

        // Tab default menampilkan antrean peminjaman aktif beserta tombol Kembalikan.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian')
            ->assertOk()
            ->assertSee('Peminjaman Aktif')
            ->assertSee('Riwayat Pengembalian')
            ->assertSee('PEMINJAMAN AKTIF')
            ->assertSee('Buku Tab Aktif')
            ->assertDontSee('Buku Tab Riwayat')
            ->assertSee(route('pustakawan.peminjaman.kembalikan', $idPeminjamanAktif), false);

        // Tab riwayat menampilkan data yang sudah dikembalikan dengan aksi Detail.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian?tab=riwayat')
            ->assertOk()
            ->assertSee('RIWAYAT PENGEMBALIAN')
            ->assertSee('Buku Tab Riwayat')
            ->assertDontSee('Buku Tab Aktif')
            ->assertDontSee(route('pustakawan.peminjaman.kembalikan', $idPeminjamanAktif), false)
            ->assertSee('Detail');
    }
}

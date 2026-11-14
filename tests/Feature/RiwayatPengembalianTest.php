<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiwayatPengembalianTest extends TestCase
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

    private function buatSiswa(TUser $user, string $nis = '1001'): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa ' . $nis,
            'kelas'      => 'VII A',
        ]);
    }

    private function buatBuku(int $stok = 5, string $judul = 'Buku Uji'): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'UJI-' . uniqid(),
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

    /**
     * Buat peminjaman langsung di database (stok buku dianggap sudah berkurang).
     */
    private function buatPeminjaman(int $idSiswa, int $idBuku, ?Carbon $jatuhTempo = null): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => ($jatuhTempo ?? Carbon::today()->subDays(3))->toDateString(),
            'status'              => 'Dipinjam',
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    public function test_tombol_detail_membuka_detail_peminjaman_dan_menampilkan_ajukan_pengembalian(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswadetail');
        $idSiswa = $this->buatSiswa($siswaUser, '2001');
        $idBuku = $this->buatBuku(4, 'Matematika Kelas VII');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        // Daftar riwayat: tombol Detail mengarah ke detail peminjaman, bukan katalog.
        $this->actingAs($siswaUser)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee(route('riwayat.show', $idPeminjaman), false)
            ->assertDontSee(route('buku.show', $idBuku), false)
            ->assertSee('Terlambat');

        // Halaman detail menampilkan data peminjaman dan tombol Ajukan Pengembalian.
        $this->actingAs($siswaUser)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('Detail Peminjaman')
            ->assertSee('Matematika Kelas VII')
            ->assertSee('Siswa 2001')
            ->assertSee('Terlambat')
            ->assertSee('Ajukan Pengembalian');
    }

    public function test_siswa_lain_tidak_bisa_membuka_atau_mengajukan_pengembalian(): void
    {
        $pemilik = $this->buatUser('Siswa', 'pemilik');
        $idSiswa = $this->buatSiswa($pemilik, '2002');
        $idBuku = $this->buatBuku(4);
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $siswaLain = $this->buatUser('Siswa', 'siswalain');
        $this->buatSiswa($siswaLain, '2003');

        $this->actingAs($siswaLain)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertNotFound();

        $this->actingAs($siswaLain)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertNotFound();

        $this->assertNull(
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('pengajuan_kembali_at')
        );
    }

    public function test_pengajuan_pengembalian_tersimpan_dan_status_menunggu_pengembalian(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswaajukan');
        $idSiswa = $this->buatSiswa($siswaUser, '2004');
        $idBuku = $this->buatBuku(4, 'Bahasa Indonesia');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect(route('riwayat'))
            ->assertSessionHas('success');

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertNotNull($peminjaman->pengajuan_kembali_at);

        // Status utama tetap Dipinjam dan stok belum berubah sampai diproses pustakawan.
        $this->assertSame('Dipinjam', $peminjaman->status);
        $this->assertSame(4, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        // Siswa kembali ke riwayat dan melihat status barunya.
        $this->actingAs($siswaUser)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee('Menunggu Pengembalian');

        // Halaman detail tidak lagi menampilkan tombol pengajuan.
        $this->actingAs($siswaUser)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('Menunggu Pengembalian')
            ->assertDontSee('Ajukan Pengembalian');

        // Pengajuan ganda tidak boleh menimpa waktu pengajuan pertama.
        $waktuPengajuan = $peminjaman->pengajuan_kembali_at;

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect(route('riwayat'))
            ->assertSessionHas('info');

        $this->assertSame(
            $waktuPengajuan,
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('pengajuan_kembali_at')
        );
    }

    public function test_pustakawan_melihat_status_dan_memproses_pengajuan_pengembalian(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswaprocess');
        $idSiswa = $this->buatSiswa($siswaUser, '2005');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanprocess');
        $idBuku = $this->buatBuku(4, 'Ilmu Pengetahuan Alam');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect(route('riwayat'));

        // Pustakawan melihat status pengajuan pada daftar, detail, dan form pengembalian.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman')
            ->assertOk()
            ->assertSee('Menunggu Pengembalian');

        $this->actingAs($pustakawan)
            ->get("/pustakawan/peminjaman/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('Menunggu Pengembalian');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian/create')
            ->assertOk()
            ->assertSee('pengajuan pengembalian');

        // Pustakawan mencatat pengembalian: status berubah dan stok kembali.
        $this->actingAs($pustakawan)
            ->post('/pustakawan/pengembalian', [
                'id_peminjaman'   => $idPeminjaman,
                'tanggal_kembali' => Carbon::today()->toDateString(),
            ])
            ->assertRedirect(route('pustakawan.pengembalian'));

        $pengembalian = DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertNotNull($pengembalian);
        $this->assertSame('Belum Lunas', $pengembalian->status_bayar);
        $this->assertSame(
            'Menunggu Pembayaran',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );
        $this->assertSame(5, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        // Riwayat siswa belum menyatakan pengembalian selesai sebelum denda lunas.
        $this->actingAs($siswaUser)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee('Menunggu Pembayaran');

        // Setelah denda dilunasi, pengembalian baru dinyatakan selesai.
        $this->actingAs($pustakawan)
            ->patch(route('pustakawan.denda.lunas', $pengembalian->id_pengembalian))
            ->assertRedirect();

        $this->assertSame(
            'Dikembalikan',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );

        $this->actingAs($siswaUser)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee('Dikembalikan');
    }

    public function test_filter_riwayat_menunggu_pengembalian(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswafilter');
        $idSiswa = $this->buatSiswa($siswaUser, '2006');
        $idBuku = $this->buatBuku(4, 'Buku Diajukan');
        $idDiajukan = $this->buatPeminjaman($idSiswa, $idBuku);
        $idBukuLain = $this->buatBuku(4, 'Buku Belum Diajukan');
        $idBelum = $this->buatPeminjaman($idSiswa, $idBukuLain);

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idDiajukan}/pengembalian")
            ->assertRedirect(route('riwayat'));

        $this->actingAs($siswaUser)
            ->get('/riwayat?status=menunggu-pengembalian')
            ->assertOk()
            ->assertSee('Buku Diajukan')
            ->assertDontSee('Buku Belum Diajukan');

        // Peminjaman yang belum diajukan tetap ada di riwayat tanpa filter.
        $this->actingAs($siswaUser)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee('Buku Diajukan')
            ->assertSee('Buku Belum Diajukan');

        $this->assertNull(
            DB::table('peminjaman')->where('id_peminjaman', $idBelum)->value('pengajuan_kembali_at')
        );
    }

    public function test_tombol_pengembalian_di_katalog_hanya_untuk_peminjaman_aktif_siswa(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswakatalog');
        $idSiswa = $this->buatSiswa($siswaUser, '2007');
        $idBuku = $this->buatBuku(4, 'Buku Katalog');

        // Belum meminjam: tombol pengembalian tidak ditampilkan.
        $this->actingAs($siswaUser)
            ->get("/buku/{$idBuku}")
            ->assertOk()
            ->assertDontSee('Ajukan pengembalian');

        // Setelah meminjam: tombol mengarah ke alur pengajuan yang benar.
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($siswaUser)
            ->get("/buku/{$idBuku}")
            ->assertOk()
            ->assertSee('Ajukan pengembalian')
            ->assertSee(route('riwayat.pengembalian', $idPeminjaman), false);

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect(route('riwayat'));

        // Setelah diajukan: tombol diganti informasi menunggu diproses.
        $this->actingAs($siswaUser)
            ->get("/buku/{$idBuku}")
            ->assertOk()
            ->assertSee('menunggu diproses pustakawan')
            ->assertDontSee(route('riwayat.pengembalian', $idPeminjaman), false);
    }

    public function test_staf_tidak_melihat_aksi_pengembalian_siswa_di_katalog(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswakatalogstaf');
        $idSiswa = $this->buatSiswa($siswaUser, '2008');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawankatalog');
        $admin = $this->buatUser('Admin', 'adminkatalog');
        $idBuku = $this->buatBuku(4, 'Buku Katalog Staf');
        $this->buatPeminjaman($idSiswa, $idBuku);

        // Halaman katalog tetap dapat dibaca staf, tetapi aksi pengembalian
        // milik siswa tidak ditampilkan.
        $this->actingAs($pustakawan)
            ->get("/buku/{$idBuku}")
            ->assertOk()
            ->assertDontSee('Ajukan pengembalian');

        $this->actingAs($admin)
            ->get("/buku/{$idBuku}")
            ->assertOk()
            ->assertDontSee('Ajukan pengembalian');
    }

    public function test_pustakawan_dan_admin_tidak_bisa_mengajukan_pengembalian_siswa(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswarole');
        $idSiswa = $this->buatSiswa($siswaUser, '2009');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanrole');
        $admin = $this->buatUser('Admin', 'adminrole');
        $idBuku = $this->buatBuku(4, 'Buku Role');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $this->actingAs($pustakawan)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect('/pustakawan');

        $this->actingAs($admin)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect('/admin');

        $this->assertNull(
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('pengajuan_kembali_at')
        );
    }
}

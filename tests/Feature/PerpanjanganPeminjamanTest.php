<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerpanjanganPeminjamanTest extends TestCase
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

    private function buatBuku(int $stok = 3, string $judul = 'Buku Perpanjangan'): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'PJG-' . uniqid(),
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
        ?Carbon $jatuhTempo = null,
        array $tambahan = []
    ): int {
        $idPeminjaman = DB::table('peminjaman')->insertGetId(array_merge([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(3)->toDateString(),
            'tanggal_jatuh_tempo' => ($jatuhTempo ?? Carbon::today()->addDays(4))->toDateString(),
            'status'              => 'Dipinjam',
        ], $tambahan));

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    public function test_siswa_mengajukan_perpanjangan_dan_pustakawan_menyetujui(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswaperpanjang');
        $idSiswa = $this->buatSiswa($siswaUser);
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanperpanjang');
        $idBuku = $this->buatBuku(3, 'Buku Diajukan Perpanjangan');
        $jatuhTempoAwal = Carbon::today()->addDays(2);
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, $jatuhTempoAwal);

        // Detail peminjaman aktif menampilkan tombol pengajuan perpanjangan.
        $this->actingAs($siswaUser)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('Ajukan Perpanjangan')
            ->assertSee('Ajukan Pengembalian');

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPeminjaman}/perpanjangan")
            ->assertRedirect(route('riwayat.show', $idPeminjaman))
            ->assertSessionHas('success');

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertSame('Menunggu', $peminjaman->perpanjangan_status);
        $this->assertNotNull($peminjaman->perpanjangan_diminta_at);
        $this->assertSame('Dipinjam', $peminjaman->status);
        $this->assertSame(
            $jatuhTempoAwal->toDateString(),
            Carbon::parse($peminjaman->tanggal_jatuh_tempo)->toDateString()
        );

        // Siswa melihat bahwa pengajuan sedang menunggu verifikasi.
        $this->actingAs($siswaUser)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('menunggu verifikasi pustakawan')
            ->assertDontSee('Ajukan Perpanjangan');

        // Pustakawan melihat pengajuan beserta tombol verifikasi.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman')
            ->assertOk()
            ->assertSee('Perpanjangan Menunggu')
            ->assertSee(route('pustakawan.peminjaman.perpanjangan.setujui', $idPeminjaman), false)
            ->assertSee(route('pustakawan.peminjaman.perpanjangan.tolak', $idPeminjaman), false);

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.peminjaman'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/perpanjangan/setujui")
            ->assertRedirect(route('pustakawan.peminjaman'))
            ->assertSessionHas('success');

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertSame('Disetujui', $peminjaman->perpanjangan_status);
        $this->assertSame(
            $jatuhTempoAwal->copy()->addDays(7)->toDateString(),
            Carbon::parse($peminjaman->tanggal_jatuh_tempo)->toDateString()
        );

        // Stok tidak berubah karena peminjaman masih aktif.
        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        $this->actingAs($siswaUser)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('Pengajuan perpanjangan disetujui');
    }

    public function test_pustakawan_menolak_perpanjangan_tanpa_mengubah_jatuh_tempo(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswatolak');
        $idSiswa = $this->buatSiswa($siswaUser, '4002');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawantolak');
        $idBuku = $this->buatBuku(3, 'Buku Ditolak Perpanjangan');
        $jatuhTempoAwal = Carbon::today()->addDays(5);
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, $jatuhTempoAwal, [
            'perpanjangan_status'     => 'Menunggu',
            'perpanjangan_diminta_at' => now(),
        ]);

        $this->actingAs($pustakawan)
            ->from(route('pustakawan.peminjaman'))
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/perpanjangan/tolak")
            ->assertRedirect(route('pustakawan.peminjaman'))
            ->assertSessionHas('success');

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertSame('Ditolak', $peminjaman->perpanjangan_status);
        $this->assertSame(
            $jatuhTempoAwal->toDateString(),
            Carbon::parse($peminjaman->tanggal_jatuh_tempo)->toDateString()
        );

        $this->actingAs($siswaUser)
            ->get("/riwayat/{$idPeminjaman}")
            ->assertOk()
            ->assertSee('Pengajuan perpanjangan ditolak oleh pustakawan');
    }

    public function test_pengajuan_perpanjangan_ditolak_saat_terlambat_atau_sudah_diajukan(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswabatas');
        $idSiswa = $this->buatSiswa($siswaUser, '4003');
        $idBuku = $this->buatBuku(3, 'Buku Batas Perpanjangan');

        $idTerlambat = $this->buatPeminjaman($idSiswa, $idBuku, Carbon::today()->subDays(2));
        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idTerlambat}/perpanjangan")
            ->assertSessionHas('error');

        $this->assertNull(
            DB::table('peminjaman')->where('id_peminjaman', $idTerlambat)->value('perpanjangan_status')
        );

        $waktuPengajuan = now()->subHours(2);
        $idMenunggu = $this->buatPeminjaman($idSiswa, $idBuku, Carbon::today()->addDays(3), [
            'perpanjangan_status'     => 'Menunggu',
            'perpanjangan_diminta_at' => $waktuPengajuan,
        ]);
        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idMenunggu}/perpanjangan")
            ->assertSessionHas('info');

        $this->assertSame(
            $waktuPengajuan->toDateTimeString(),
            Carbon::parse(DB::table('peminjaman')->where('id_peminjaman', $idMenunggu)->value('perpanjangan_diminta_at'))->toDateTimeString()
        );

        $idPengembalianDiajukan = $this->buatPeminjaman($idSiswa, $idBuku, Carbon::today()->addDays(3), [
            'pengajuan_kembali_at' => now(),
        ]);
        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPengembalianDiajukan}/perpanjangan")
            ->assertSessionHas('info');

        $this->assertNull(
            DB::table('peminjaman')->where('id_peminjaman', $idPengembalianDiajukan)->value('perpanjangan_status')
        );
    }

    public function test_pengajuan_pengembalian_membatalkan_perpanjangan_yang_menunggu(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswabatal');
        $idSiswa = $this->buatSiswa($siswaUser, '4004');
        $idBuku = $this->buatBuku(3, 'Buku Batal Perpanjangan');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, Carbon::today()->addDays(4), [
            'perpanjangan_status'     => 'Menunggu',
            'perpanjangan_diminta_at' => now(),
        ]);

        $this->actingAs($siswaUser)
            ->post("/riwayat/{$idPeminjaman}/pengembalian")
            ->assertRedirect(route('riwayat'));

        $peminjaman = DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertNotNull($peminjaman->pengajuan_kembali_at);
        $this->assertNull($peminjaman->perpanjangan_status);
        $this->assertNull($peminjaman->perpanjangan_diminta_at);
    }

    public function test_siswa_lain_dan_staf_tidak_bisa_mengajukan_perpanjangan(): void
    {
        $pemilik = $this->buatUser('Siswa', 'pemilikperpanjang');
        $idSiswa = $this->buatSiswa($pemilik, '4005');
        $idBuku = $this->buatBuku(3, 'Buku Milik');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku);

        $siswaLain = $this->buatUser('Siswa', 'siswalainperpanjang');
        $this->buatSiswa($siswaLain, '4006');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanperpanjangrole');

        $this->actingAs($siswaLain)
            ->post("/riwayat/{$idPeminjaman}/perpanjangan")
            ->assertNotFound();

        $this->actingAs($pustakawan)
            ->post("/riwayat/{$idPeminjaman}/perpanjangan")
            ->assertRedirect('/pustakawan');

        $this->assertNull(
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('perpanjangan_status')
        );
    }

    public function test_verifikasi_perpanjangan_hanya_bisa_dilakukan_pustakawan(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswaverifikasi');
        $idSiswa = $this->buatSiswa($siswaUser, '4007');
        $admin = $this->buatUser('Admin', 'adminverifikasi');
        $idBuku = $this->buatBuku(3, 'Buku Verifikasi');
        $idPeminjaman = $this->buatPeminjaman($idSiswa, $idBuku, null, [
            'perpanjangan_status'     => 'Menunggu',
            'perpanjangan_diminta_at' => now(),
        ]);

        $this->actingAs($siswaUser)
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/perpanjangan/setujui")
            ->assertRedirect('/dashboard');

        $this->actingAs($admin)
            ->post("/pustakawan/peminjaman/{$idPeminjaman}/perpanjangan/setujui")
            ->assertRedirect('/admin');

        $this->assertSame(
            'Menunggu',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('perpanjangan_status')
        );
    }
}

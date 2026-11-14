<?php

namespace Tests\Feature;

use App\Models\TUser;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SipusRoleAndFlowTest extends TestCase
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

    public function test_login_mengarahkan_dashboard_sesuai_role(): void
    {
        $this->buatUser('Admin', 'adminuji');
        $this->buatUser('Pustakawan', 'pustakawanuji');
        $this->buatUser('Siswa', 'siswauji');

        $this->post('/login', ['username' => 'adminuji', 'password' => 'secret'])
            ->assertRedirect('/dashboard');

        $this->post('/login', ['username' => 'pustakawanuji', 'password' => 'secret'])
            ->assertRedirect('/dashboard');

        $this->post('/login', ['username' => 'siswauji', 'password' => 'secret'])
            ->assertRedirect('/dashboard');
    }

    public function test_dashboard_dialihkan_sesuai_role(): void
    {
        $admin = $this->buatUser('Admin', 'admin2');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan2');
        $siswaUser = $this->buatUser('Siswa', 'siswa2');
        $this->buatSiswa($siswaUser, '1002');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs($pustakawan)->get('/dashboard')->assertRedirect('/pustakawan');
        $this->actingAs($siswaUser)->get('/dashboard')->assertOk();
    }

    public function test_setiap_role_tidak_bisa_membuka_area_role_lain(): void
    {
        $admin = $this->buatUser('Admin', 'admin3');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan3');
        $siswaUser = $this->buatUser('Siswa', 'siswa3');
        $this->buatSiswa($siswaUser, '1003');

        // Siswa tidak boleh masuk admin/pustakawan.
        $this->actingAs($siswaUser)->get('/admin')->assertRedirect('/dashboard');
        $this->actingAs($siswaUser)->get('/pustakawan')->assertRedirect('/dashboard');
        $this->actingAs($siswaUser)->get('/admin/buku')->assertRedirect('/dashboard');

        // Admin tidak boleh masuk area operasional pustakawan.
        $this->actingAs($admin)->get('/pustakawan')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/pustakawan/pengajuan')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/pustakawan/peminjaman')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/pustakawan/keterlambatan')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/pustakawan/pengembalian')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/pustakawan/denda')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/pustakawan/laporan')->assertRedirect('/admin');

        // Pustakawan tidak boleh masuk area admin.
        $this->actingAs($pustakawan)->get('/admin')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/kategori')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/buku')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/anggota')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/kelas')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/akun')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/ketentuan-denda')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/pengaturan')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/statistik')->assertRedirect('/pustakawan');
        $this->actingAs($pustakawan)->get('/admin/laporan')->assertRedirect('/pustakawan');

        // Halaman pengajuan siswa hanya untuk siswa.
        $this->actingAs($admin)->get('/pengajuan')->assertRedirect('/admin');
    }

    public function test_menu_dan_halaman_sesuai_pembagian_tugas(): void
    {
        $admin = $this->buatUser('Admin', 'adminmenu');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanmenu');

        // Halaman yang menjadi hak Admin.
        foreach ([
            '/admin',
            '/admin/buku',
            '/admin/anggota',
            '/admin/kelas',
            '/admin/akun',
            '/admin/ketentuan-denda',
            '/admin/pengaturan',
            '/admin/statistik',
            '/admin/laporan',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // Halaman operasional yang menjadi hak Pustakawan.
        foreach ([
            '/pustakawan',
            '/pustakawan/pengajuan',
            '/pustakawan/persetujuan',
            '/pustakawan/peminjaman',
            '/pustakawan/keterlambatan',
            '/pustakawan/pengembalian',
            '/pustakawan/denda',
            '/pustakawan/laporan',
        ] as $url) {
            $this->actingAs($pustakawan)->get($url)->assertOk();
        }

        // Statistik tetap bukan milik Pustakawan (route khusus Admin).
        $this->actingAs($pustakawan)->get('/pustakawan/statistik')->assertNotFound();

        // Sidebar Pustakawan memuat menu operasional sesuai pembagian tugas.
        $this->actingAs($pustakawan)->get('/pustakawan')
            ->assertOk()
            ->assertSee('Pengajuan Peminjaman')
            ->assertSee('Persetujuan Peminjaman')
            ->assertSee('Transaksi Peminjaman')
            ->assertSee('Keterlambatan')
            ->assertSee('Denda')
            ->assertSee('Laporan Peminjaman dan Pengembalian')
            ->assertDontSee('Statistik')
            ->assertDontSee('Laporan Keseluruhan');

        // Sidebar Admin memuat menu pengelolaan, bukan menu operasional Pustakawan.
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Data Buku')
            ->assertSee('Pengguna')
            ->assertSee('Pengaturan Sistem')
            ->assertSee('Laporan Keseluruhan')
            ->assertDontSee('Pengajuan Peminjaman')
            ->assertDontSee('Transaksi Peminjaman');
    }

    public function test_alur_pengajuan_persetujuan_dan_pengembalian(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa4');
        $idSiswa = $this->buatSiswa($siswaUser, '1004');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan4');
        $idBuku = $this->buatBuku(5);

        // Siswa mengajukan peminjaman: stok belum berkurang.
        $this->actingAs($siswaUser)
            ->post('/pengajuan', ['buku_id' => $idBuku])
            ->assertRedirect('/pengajuan');

        $peminjaman = DB::table('peminjaman')->latest('id_peminjaman')->first();
        $this->assertNotNull($peminjaman);
        $this->assertSame('Menunggu', $peminjaman->status);
        $this->assertSame(5, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        // Pustakawan menyetujui: status berubah dan stok berkurang.
        $this->actingAs($pustakawan)
            ->patch("/pustakawan/pengajuan/{$peminjaman->id_peminjaman}/approve")
            ->assertRedirect();

        $this->assertSame('Dipinjam', DB::table('peminjaman')->where('id_peminjaman', $peminjaman->id_peminjaman)->value('status'));
        $this->assertSame(4, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        // Menyetujui dua kali tidak boleh mengurangi stok lagi.
        $this->actingAs($pustakawan)
            ->patch("/pustakawan/pengajuan/{$peminjaman->id_peminjaman}/approve")
            ->assertRedirect();
        $this->assertSame(4, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        // Pustakawan mencatat pengembalian tepat waktu: stok kembali, status Dikembalikan.
        $this->actingAs($pustakawan)
            ->post('/pustakawan/pengembalian', [
                'id_peminjaman'   => $peminjaman->id_peminjaman,
                'tanggal_kembali' => Carbon::today()->toDateString(),
            ])
            ->assertRedirect(route('pustakawan.pengembalian'));

        $this->assertSame('Dikembalikan', DB::table('peminjaman')->where('id_peminjaman', $peminjaman->id_peminjaman)->value('status'));
        $this->assertSame(5, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        $pengembalian = DB::table('pengembalian')->where('id_peminjaman', $peminjaman->id_peminjaman)->first();
        $this->assertNotNull($pengembalian);
        $this->assertEquals(0, (float) $pengembalian->denda);
        $this->assertSame('Tepat Waktu', $pengembalian->kategori);
    }

    public function test_denda_memakai_ketentuan_admin(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa5');
        $idSiswa = $this->buatSiswa($siswaUser, '1005');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan5');
        $idBuku = $this->buatBuku(3);

        Pengaturan::set('denda_per_hari', 700);

        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->subDays(3)->toDateString(),
            'status'              => 'Dipinjam',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);
        DB::table('buku')->where('id_buku', $idBuku)->decrement('stok');

        $this->actingAs($pustakawan)
            ->post('/pustakawan/pengembalian', [
                'id_peminjaman'   => $idPeminjaman,
                'tanggal_kembali' => Carbon::today()->toDateString(),
            ])
            ->assertRedirect(route('pustakawan.pengembalian'));

        $pengembalian = DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->first();
        $this->assertNotNull($pengembalian);
        $this->assertEquals(2100, (float) $pengembalian->denda); // 3 hari x Rp700
        $this->assertSame('Terlambat', $pengembalian->kategori);
        $this->assertSame('Belum Lunas', $pengembalian->status_bayar);
        $this->assertSame('Menunggu Pembayaran', DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status'));
        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_stok_tidak_cukup_tidak_bisa_dipinjam(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa6');
        $idSiswa = $this->buatSiswa($siswaUser, '1006');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan6');
        $idBuku = $this->buatBuku(0);

        $this->actingAs($siswaUser)
            ->post('/pengajuan', ['buku_id' => $idBuku])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('peminjaman')->count());

        $this->actingAs($pustakawan)
            ->post('/pustakawan/peminjaman', [
                'id_siswa'            => $idSiswa,
                'id_buku'             => $idBuku,
                'tanggal_pinjam'      => Carbon::today()->toDateString(),
                'tanggal_jatuh_tempo' => Carbon::today()->addDays(7)->toDateString(),
            ])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('peminjaman')->count());
    }

    public function test_penolakan_pengajuan_tidak_mengubah_stok(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa7');
        $idSiswa = $this->buatSiswa($siswaUser, '1007');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan7');
        $idBuku = $this->buatBuku(2);

        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->addDays(7)->toDateString(),
            'status'              => 'Menunggu',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        $this->actingAs($pustakawan)
            ->patch("/pustakawan/pengajuan/{$idPeminjaman}/reject")
            ->assertRedirect();

        $this->assertSame('Ditolak', DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status'));
        $this->assertSame(2, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_admin_mengelola_kategori_dan_sinkron_ke_buku(): void
    {
        $admin = $this->buatUser('Admin', 'admin8');
        $idBuku = $this->buatBuku(1);
        $idKategori = DB::table('buku')->where('id_buku', $idBuku)->value('id_kategori');

        $this->actingAs($admin)->get('/admin/kategori')->assertOk();

        $this->actingAs($admin)
            ->put("/admin/kategori/{$idKategori}", [
                'nama_kategori' => 'Pelajaran Baru',
                'keterangan'    => 'Diperbarui',
            ])
            ->assertRedirect(route('admin.kategori'));

        $this->assertSame('Pelajaran Baru', DB::table('buku')->where('id_buku', $idBuku)->value('kategori'));

        // Kategori yang masih dipakai tidak boleh dihapus.
        $this->actingAs($admin)
            ->delete("/admin/kategori/{$idKategori}")
            ->assertRedirect(route('admin.kategori'));

        $this->assertDatabaseHas('kategori_buku', ['id_kategori' => $idKategori]);
    }

    public function test_katalog_buku_paket_dikelompokkan_per_kelas(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa9');
        $this->buatSiswa($siswaUser, '1009');

        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Buku paket',
        ]);

        // Kategori lain tetap ada di database, tetapi tidak boleh tampil di katalog siswa.
        foreach (['Cerita', 'Pelajaran Lain'] as $namaKategoriLain) {
            $idLain = DB::table('kategori_buku')->insertGetId([
                'nama_kategori' => $namaKategoriLain,
                'keterangan'    => 'Kategori lain',
            ]);

            DB::table('buku')->insert([
                'id_kategori'  => $idLain,
                'no_seri_buku' => 'LAIN-' . $idLain,
                'judul'        => 'Buku ' . $namaKategoriLain,
                'pengarang'    => 'Penulis',
                'penerbit'     => 'Penerbit',
                'tahun_terbit' => 2022,
                'kategori'     => $namaKategoriLain,
                'lokasi_rak'   => 'R-02',
                'stok'         => 3,
                'created_at'   => now(),
            ]);
        }

        $mapel = [
            'Pendidikan Agama Islam dan Budi Pekerti',
            'Pendidikan Pancasila',
            'Bahasa Indonesia',
            'Matematika',
            'Ilmu Pengetahuan Alam',
            'Ilmu Pengetahuan Sosial',
            'Bahasa Inggris',
            'Informatika',
        ];

        $nomor = 1;
        foreach (['VII', 'VIII', 'IX'] as $kelas) {
            foreach ($mapel as $nama) {
                DB::table('buku')->insert([
                    'id_kategori'  => $idKategori,
                    'no_seri_buku' => 'BKP-' . str_pad((string) $nomor++, 3, '0', STR_PAD_LEFT),
                    'judul'        => $nama . ' Kelas ' . $kelas,
                    'pengarang'    => 'Tim Penyusun',
                    'penerbit'     => 'Kemendikbudristek',
                    'tahun_terbit' => 2022,
                    'kategori'     => 'Pelajaran',
                    'lokasi_rak'   => 'R-01',
                    'stok'         => 5,
                    'created_at'   => now(),
                ]);
            }
        }

        $index = $this->actingAs($siswaUser)->get('/katalog');
        $index->assertOk();
        $index->assertSee('Pelajaran');
        $index->assertSee('Cerita');
        $index->assertDontSee('>Pelajaran Lain<', false);

        // Siswa yang membuka kategori "Pelajaran Lain" lewat URL dialihkan ke katalog.
        $this->actingAs($siswaUser)
            ->get('/katalog/kategori/Pelajaran Lain')
            ->assertRedirect(route('katalog'));

        // Buku cerita tetap bisa dibuka siswa.
        $this->actingAs($siswaUser)
            ->get('/katalog/kategori/Cerita')
            ->assertOk()
            ->assertSee('Buku Cerita');

        $res = $this->actingAs($siswaUser)->get('/katalog/kategori/Pelajaran');
        $res->assertOk();
        $res->assertSeeInOrder(['Kelas VII', 'Kelas VIII', 'Kelas IX']);

        // Setiap kelas harus menampilkan tepat 8 buku.
        $this->assertSame(3, substr_count($res->getContent(), '<span>8 buku</span>'));

        foreach (['VII', 'VIII', 'IX'] as $kelas) {
            $this->assertStringContainsString('Informatika Kelas ' . $kelas, $res->getContent());
        }
    }

    public function test_status_pengembalian_tidak_bisa_diedit(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa10');
        $idSiswa = $this->buatSiswa($siswaUser, '1010');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan10');
        $idBuku = $this->buatBuku(3);

        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->subDays(3)->toDateString(),
            'status'              => 'Dikembalikan',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);
        $idPengembalian = DB::table('pengembalian')->insertGetId([
            'id_peminjaman'   => $idPeminjaman,
            'tanggal_kembali' => Carbon::today()->toDateString(),
            'denda'           => 1500,
            'kategori'        => 'Terlambat',
            'status_bayar'    => 'Belum Lunas',
        ]);

        // Tab riwayat pengembalian hanya menyediakan aksi Detail.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian?tab=riwayat')
            ->assertOk()
            ->assertSee('RIWAYAT PENGEMBALIAN')
            ->assertSee('Detail')
            ->assertDontSee("/pustakawan/pengembalian/{$idPengembalian}/edit", false);

        // Detail pengembalian bersifat baca saja.
        $this->actingAs($pustakawan)
            ->get("/pustakawan/pengembalian/{$idPengembalian}")
            ->assertOk()
            ->assertSee('Terlambat')
            ->assertDontSee("/pustakawan/pengembalian/{$idPengembalian}/edit", false);

        // Route edit dan update pengembalian sudah tidak tersedia.
        $this->actingAs($pustakawan)
            ->get("/pustakawan/pengembalian/{$idPengembalian}/edit")
            ->assertNotFound();

        $this->actingAs($pustakawan)
            ->put("/pustakawan/pengembalian/{$idPengembalian}", [
                'tanggal_kembali' => Carbon::today()->toDateString(),
                'kategori'        => 'Tepat Waktu',
                'denda'           => 0,
                'status_bayar'    => 'Lunas',
            ])
            ->assertStatus(405);

        $row = DB::table('pengembalian')->where('id_pengembalian', $idPengembalian)->first();
        $this->assertSame('Terlambat', $row->kategori);
        $this->assertSame('Belum Lunas', $row->status_bayar);
        $this->assertEquals(1500, (float) $row->denda);
    }

    public function test_status_peminjaman_tidak_bisa_diubah_menjadi_dikembalikan_lewat_form_edit(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa10b');
        $idSiswa = $this->buatSiswa($siswaUser, '1010');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan10b');
        $idBuku = $this->buatBuku(3);

        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->subDays(3)->toDateString(),
            'status'              => 'Dipinjam',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        // Status Dikembalikan hanya boleh dihasilkan oleh tombol Kembalikan,
        // bukan lewat form edit peminjaman.
        $this->actingAs($pustakawan)
            ->from("/pustakawan/peminjaman/{$idPeminjaman}/edit")
            ->put("/pustakawan/peminjaman/{$idPeminjaman}", [
                'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
                'tanggal_jatuh_tempo' => Carbon::today()->addDays(4)->toDateString(),
                'status'              => 'Dikembalikan',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            'Dipinjam',
            DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status')
        );
        $this->assertSame(
            0,
            DB::table('pengembalian')->where('id_peminjaman', $idPeminjaman)->count()
        );
    }

    public function test_edit_status_dari_keterlambatan_tetap_di_menu_keterlambatan(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa11');
        $idSiswa = $this->buatSiswa($siswaUser, '1011');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan11');
        $idBuku = $this->buatBuku(3);

        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->subDays(3)->toDateString(),
            'status'              => 'Dipinjam',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        // Halaman Keterlambatan memakai filter & tautan edit yang tetap di menu ini.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/keterlambatan')
            ->assertOk()
            ->assertSee('Keterlambatan')
            ->assertSee('action="' . route('pustakawan.keterlambatan') . '"', false)
            ->assertSee('asal=keterlambatan', false);

        // Form edit memakai konteks Keterlambatan.
        $this->actingAs($pustakawan)
            ->get("/pustakawan/peminjaman/{$idPeminjaman}/edit?asal=keterlambatan")
            ->assertOk()
            ->assertSee('Edit Keterlambatan');

        // Setelah menyimpan, kembali ke Keterlambatan (bukan Transaksi Peminjaman).
        $this->actingAs($pustakawan)
            ->put("/pustakawan/peminjaman/{$idPeminjaman}", [
                'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
                'tanggal_jatuh_tempo' => Carbon::today()->addDays(4)->toDateString(),
                'status'              => 'Dipinjam',
                'asal'                => 'keterlambatan',
            ])
            ->assertRedirect(route('pustakawan.keterlambatan'));
    }

    public function test_kolom_siswa_pengajuan_memakai_nama_siswa_yang_terhubung_akun(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa12');
        $this->buatSiswa($siswaUser, '1012');

        // Nama asli ada di data siswa; nama akun sengaja dibuat berbeda
        // untuk memastikan kolom Siswa memakai data siswa yang tertaut.
        DB::table('siswa')->where('id_user', $siswaUser->id_user)->update([
            'nama_siswa' => 'Nama Asli Pemohon',
        ]);
        DB::table('t_user')->where('id_user', $siswaUser->id_user)->update([
            'name' => 'Nama Akun Beda',
        ]);

        $pustakawan = $this->buatUser('Pustakawan', 'pustakawan12');
        $idBuku = $this->buatBuku(3);

        // Pengajuan dibuat oleh siswa yang sedang login.
        $this->actingAs($siswaUser)
            ->post('/pengajuan', ['buku_id' => $idBuku])
            ->assertRedirect('/pengajuan');

        // Form pengajuan menampilkan identitas siswa secara otomatis.
        $this->actingAs($siswaUser)
            ->get("/pengajuan/{$idBuku}/create")
            ->assertOk()
            ->assertSee('Nama Asli Pemohon')
            ->assertSee('1012');

        // Kolom "Siswa" memakai nama dari data siswa yang terhubung ke akun.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengajuan')
            ->assertOk()
            ->assertSee('Nama Asli Pemohon')
            ->assertDontSee('Nama Akun Beda');

        // Halaman pengajuan milik siswa juga menampilkan nama siswanya.
        $this->actingAs($siswaUser)
            ->get('/pengajuan')
            ->assertOk()
            ->assertSee('Nama Asli Pemohon')
            ->assertDontSee('Nama Akun Beda');

        // Bila nama siswa kosong, pakai nama akun sebagai cadangan.
        DB::table('siswa')->where('id_user', $siswaUser->id_user)->update(['nama_siswa' => '']);

        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengajuan')
            ->assertOk()
            ->assertSee('Nama Akun Beda');

        $this->actingAs($siswaUser)
            ->get('/pengajuan')
            ->assertOk()
            ->assertSee('Nama Akun Beda');
    }

    public function test_update_profil_siswa_langsung_mengubah_nama_di_daftar_pengajuan(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswa13');
        $this->buatSiswa($siswaUser, '1013');
        $idBuku = $this->buatBuku(3);

        $this->actingAs($siswaUser)
            ->post('/pengajuan', ['buku_id' => $idBuku])
            ->assertRedirect('/pengajuan');

        // Siswa mengganti Nama Lengkap pada halaman profil.
        $this->actingAs($siswaUser)
            ->put('/profil', [
                'username'   => 'siswa13',
                'nis'        => '1013',
                'nama_siswa' => 'Nama Baru Siswa',
                'kelas'      => 'VII A',
            ])
            ->assertRedirect('/profil');

        // Data siswa terbarui...
        $this->assertDatabaseHas('siswa', [
            'id_user'    => $siswaUser->id_user,
            'nama_siswa' => 'Nama Baru Siswa',
        ]);

        // ...dan kolom "Siswa" langsung mengikuti nama baru tersebut.
        $this->actingAs($siswaUser)
            ->get('/pengajuan')
            ->assertOk()
            ->assertSee('Nama Baru Siswa')
            ->assertDontSee('Siswa 1013');
    }
}

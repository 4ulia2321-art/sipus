<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardStokTest extends TestCase
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

    private function buatSiswa(TUser $user, string $nis = '2001'): int
    {
        return DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa ' . $nis,
            'kelas'      => 'VII A',
        ]);
    }

    private function buatBuku(int $stok = 5, string $judul = 'Buku Uji Stok', string $kategori = 'Pelajaran'): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => $kategori,
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'STOK-' . uniqid(),
            'judul'        => $judul,
            'pengarang'    => 'Penulis Uji',
            'penerbit'     => 'Penerbit Uji',
            'tahun_terbit' => 2026,
            'kategori'     => $kategori,
            'lokasi_rak'   => 'R1',
            'stok'         => $stok,
            'created_at'   => now(),
        ]);
    }

    /**
     * Pastikan kartu dashboard memakai satuan salinan yang sama:
     * total = stok tersedia + salinan yang sedang dipinjam.
     */
    private function assertStokDashboard(TUser $user, int $total, int $tersedia, int $dipinjam): void
    {
        if ($user->role === 'Admin') {
            $this->actingAs($user)
                ->get('/admin')
                ->assertOk()
                ->assertViewHas('stat', function (array $stat) use ($total, $tersedia, $dipinjam) {
                    $kartu = collect($stat)->keyBy('label');

                    return $kartu['Total Buku']['nilai'] === $total
                        && $kartu['Buku Tersedia']['nilai'] === $tersedia
                        && $kartu['Buku Dipinjam']['nilai'] === $dipinjam;
                });

            return;
        }

        if ($user->role === 'Pustakawan') {
            $this->actingAs($user)
                ->get('/pustakawan')
                ->assertOk()
                ->assertViewHas('stats', function (array $stats) use ($total, $tersedia, $dipinjam) {
                    $kartu = collect($stats)->keyBy('label');

                    return $kartu['Total Buku']['value'] === $total
                        && $kartu['Total Buku']['delta'] === $tersedia
                        && $kartu['Buku Dipinjam']['value'] === $dipinjam;
                });

            return;
        }

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('totalBuku', $total)
            ->assertViewHas('bukuTersedia', $tersedia)
            ->assertViewHas('bukuDipinjam', $dipinjam);
    }

    public function test_stok_dashboard_berkurang_saat_dipinjam_dan_kembali_saat_dikembalikan(): void
    {
        $admin = $this->buatUser('Admin', 'adminstok');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanstok');
        $siswaUser = $this->buatUser('Siswa', 'siswastok');
        $idSiswa = $this->buatSiswa($siswaUser, '2002');
        $idBuku = $this->buatBuku(3);

        // Awal: tiga salinan tersedia, belum ada yang dipinjam.
        $this->assertStokDashboard($admin, 3, 3, 0);
        $this->assertStokDashboard($pustakawan, 3, 3, 0);
        $this->assertStokDashboard($siswaUser, 3, 3, 0);

        // Pengajuan siswa belum mengurangi stok sampai disetujui.
        $this->actingAs($siswaUser)
            ->post('/pengajuan', ['buku_id' => $idBuku])
            ->assertRedirect('/pengajuan');

        $idPeminjaman = DB::table('peminjaman')->latest('id_peminjaman')->value('id_peminjaman');

        $this->assertStokDashboard($admin, 3, 3, 0);
        $this->assertStokDashboard($pustakawan, 3, 3, 0);
        $this->assertStokDashboard($siswaUser, 3, 3, 0);

        // Persetujuan: stok tersedia berkurang, salinan dipinjam bertambah.
        $this->actingAs($pustakawan)
            ->patch("/pustakawan/pengajuan/{$idPeminjaman}/approve")
            ->assertRedirect();

        $this->assertSame(2, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        $this->assertStokDashboard($admin, 3, 2, 1);
        $this->assertStokDashboard($pustakawan, 3, 2, 1);
        $this->assertStokDashboard($siswaUser, 3, 2, 1);

        // Pengembalian: stok tersedia kembali dan salinan dipinjam berkurang.
        $this->actingAs($pustakawan)
            ->post('/pustakawan/pengembalian', [
                'id_peminjaman'   => $idPeminjaman,
                'tanggal_kembali' => Carbon::today()->toDateString(),
            ])
            ->assertRedirect(route('pustakawan.pengembalian'));

        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        $this->assertStokDashboard($admin, 3, 3, 0);
        $this->assertStokDashboard($pustakawan, 3, 3, 0);
        $this->assertStokDashboard($siswaUser, 3, 3, 0);
    }

    public function test_form_edit_pustakawan_menyelaraskan_stok_saat_status_berubah(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawaneditstok');
        $siswaUser = $this->buatUser('Siswa', 'siswaeditstok');
        $idSiswa = $this->buatSiswa($siswaUser, '2004');
        $idBuku = $this->buatBuku(3);

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

        $form = [
            'tanggal_pinjam'      => Carbon::today()->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->addDays(7)->toDateString(),
        ];

        // Menunggu -> Dipinjam: satu salinan keluar dari stok tersedia.
        $this->actingAs($pustakawan)
            ->put("/pustakawan/peminjaman/{$idPeminjaman}", $form + ['status' => 'Dipinjam'])
            ->assertRedirect(route('pustakawan.peminjaman'));

        $this->assertSame('Dipinjam', DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status'));
        $this->assertSame(2, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));

        // Dipinjam -> Ditolak: salinan kembali ke stok tersedia.
        $this->actingAs($pustakawan)
            ->put("/pustakawan/peminjaman/{$idPeminjaman}", $form + ['status' => 'Ditolak'])
            ->assertRedirect(route('pustakawan.peminjaman'));

        $this->assertSame('Ditolak', DB::table('peminjaman')->where('id_peminjaman', $idPeminjaman)->value('status'));
        $this->assertSame(3, (int) DB::table('buku')->where('id_buku', $idBuku)->value('stok'));
    }

    public function test_dashboard_menghitung_jumlah_salinan_bukan_jumlah_judul(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswasalinan');
        $idSiswa = $this->buatSiswa($siswaUser, '2003');

        $idBukuA = $this->buatBuku(1, 'Buku Satu Salinan');
        $idBukuB = $this->buatBuku(4, 'Buku Empat Salinan');

        // Dua salinan buku B sedang dipinjam; stoknya sudah berkurang.
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->addDays(7)->toDateString(),
            'status'              => 'Dipinjam',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBukuB,
            'jumlah'        => 2,
        ]);
        DB::table('buku')->where('id_buku', $idBukuB)->decrement('stok', 2);

        // Stok tersedia = 1 + 2 = 3, dipinjam = 2, total salinan = 5.
        // Kalau dihitung per judul, totalnya hanya 2.
        $this->assertStokDashboard($siswaUser, 5, 3, 2);
    }

    public function test_laporan_dan_statistik_memakai_stok_salinan_yang_sama_dengan_dashboard(): void
    {
        $admin = $this->buatUser('Admin', 'adminlaporanstok');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawanlaporanstok');
        $siswaUser = $this->buatUser('Siswa', 'siswalaporanstok');
        $idSiswa = $this->buatSiswa($siswaUser, '2005');

        $idBukuA = $this->buatBuku(1, 'Buku Satu Salinan');
        $idBukuB = $this->buatBuku(4, 'Buku Empat Salinan');

        // Dua salinan buku B sedang dipinjam; stoknya sudah berkurang.
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->toDateString(),
            'tanggal_jatuh_tempo' => Carbon::today()->addDays(7)->toDateString(),
            'status'              => 'Dipinjam',
        ]);
        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBukuB,
            'jumlah'        => 2,
        ]);
        DB::table('buku')->where('id_buku', $idBukuB)->decrement('stok', 2);

        // Tersedia 3, dipinjam 2, total salinan 5 (bukan 2 judul).
        $cekStat = function (array $stat): bool {
            return $stat['buku'] === 5
                && $stat['tersedia'] === 3
                && $stat['dipinjam'] === 2;
        };

        $this->actingAs($admin)
            ->get('/admin/laporan')
            ->assertOk()
            ->assertViewHas('stat', $cekStat);

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan')
            ->assertOk()
            ->assertViewHas('stat', $cekStat);

        $this->actingAs($admin)
            ->get('/admin/statistik')
            ->assertOk()
            ->assertViewHas('stat', function (array $stat): bool {
                return $stat['buku'] === 5 && $stat['dipinjam'] === 2;
            });

        // Kartu ringkasan di halaman Data Buku juga memakai satuan salinan.
        $cekKartuBuku = function (array $stats): bool {
            $kartu = collect($stats)->keyBy('label');

            return $kartu['Total Buku']['value'] === 5
                && $kartu['Tersedia']['value'] === 3
                && $kartu['Dipinjam']['value'] === 2;
        };

        $this->actingAs($admin)
            ->get('/admin/buku')
            ->assertOk()
            ->assertViewHas('stats', $cekKartuBuku);
    }

    public function test_dashboard_siswa_tidak_menampilkan_buku_cerita_di_pratinjau(): void
    {
        $siswaUser = $this->buatUser('Siswa', 'siswahujan');
        $this->buatSiswa($siswaUser, '2006');

        // Delapan buku pelajaran berjudul Z... sengaja dibuat agar novel
        // "Hujan" akan masuk urutan alfabetis jika tidak diprioritaskan.
        for ($i = 1; $i <= 8; $i++) {
            $this->buatBuku(2, 'Z Buku Pelajaran ' . $i);
        }
        $this->buatBuku(5, 'Hujan', 'Cerita');

        $this->actingAs($siswaUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Z Buku Pelajaran 1')
            ->assertDontSee('Hujan');
    }
}

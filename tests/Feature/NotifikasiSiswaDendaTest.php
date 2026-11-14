<?php

namespace Tests\Feature;

use App\Models\TUser;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotifikasiSiswaDendaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatSiswa(string $username = 'siswa-notif'): array
    {
        $idUser = DB::table('t_user')->insertGetId([
            'username'   => $username,
            'name'       => 'Siswa Notifikasi',
            'password'   => Hash::make('secret'),
            'role'       => 'Siswa',
            'created_at' => now(),
        ]);

        $idSiswa = DB::table('siswa')->insertGetId([
            'id_user'    => $idUser,
            'nis'        => '7777',
            'nama_siswa' => 'Siswa Notifikasi',
            'kelas'      => 'VII A',
        ]);

        return [TUser::findOrFail($idUser), $idSiswa];
    }

    private function buatBuku(string $judul): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'NOTIF-' . uniqid(),
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

    private function buatPeminjaman(int $idSiswa, int $idBuku, string $jatuhTempo): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::parse($jatuhTempo)->subDays(7)->toDateString(),
            'tanggal_jatuh_tempo' => $jatuhTempo,
            'status'              => 'Dipinjam',
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku'       => $idBuku,
            'jumlah'        => 1,
        ]);

        return $idPeminjaman;
    }

    public function test_notifikasi_menampilkan_terlambat_beserta_denda(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00'));
        Pengaturan::set('denda_per_hari', 1000);

        [$siswa, $idSiswa] = $this->buatSiswa();

        $idTerlambat = $this->buatPeminjaman($idSiswa, $this->buatBuku('Buku Terlambat'), Carbon::today()->subDays(4)->toDateString());
        $this->buatPeminjaman($idSiswa, $this->buatBuku('Buku Jatuh Tempo Hari Ini'), Carbon::today()->toDateString());
        $this->buatPeminjaman($idSiswa, $this->buatBuku('Buku Akan Jatuh Tempo'), Carbon::today()->addDays(2)->toDateString());
        $this->buatPeminjaman($idSiswa, $this->buatBuku('Buku Masih Aman'), Carbon::today()->addDays(10)->toDateString());

        // Dashboard siswa tetap seperti sebelumnya (tanpa kartu notifikasi).
        $this->actingAs($siswa)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('loan-notices', false)
            ->assertDontSee('Terlambat 4 Hari')
            ->assertDontSee('Estimasi denda: Rp 4.000')
            ->assertViewMissing('notifikasiSiswa')
            // Titik lonceng tetap aktif sebagai penanda ada notifikasi.
            ->assertSee('class="dot"', false);

        // Halaman notifikasi siswa menampilkan detail denda.
        $this->actingAs($siswa)
            ->get('/notifikasi')
            ->assertOk()
            ->assertSee('Terlambat 4 Hari')
            ->assertSee('Estimasi denda: Rp 4.000')
            ->assertSee('Jatuh Tempo Hari Ini')
            ->assertSee('Akan Jatuh Tempo')
            ->assertViewHas('notifikasi', function ($items) {
                $terlambat = $items->firstWhere('jenis', 'terlambat');

                return $terlambat !== null
                    && $terlambat->denda === 4000
                    && $terlambat->keterangan_denda === 'Estimasi denda: Rp 4.000';
            });

        // Detail peminjaman juga menampilkan estimasi denda yang sama.
        $this->actingAs($siswa)
            ->get("/riwayat/{$idTerlambat}")
            ->assertOk()
            ->assertSee('sudah melewati jatuh tempo 4 hari')
            ->assertSee('Estimasi denda:')
            ->assertSee('Rp 4.000');
    }

    public function test_notifikasi_menyesuaikan_nominal_denda_dari_pengaturan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00'));
        Pengaturan::set('denda_per_hari', 750);

        [$siswa, $idSiswa] = $this->buatSiswa('siswa-notif-2');
        $this->buatPeminjaman($idSiswa, $this->buatBuku('Buku Telat Tiga Hari'), Carbon::today()->subDays(3)->toDateString());

        $this->actingAs($siswa)
            ->get('/notifikasi')
            ->assertOk()
            ->assertSee('Terlambat 3 Hari')
            ->assertSee('Estimasi denda: Rp 2.250');
    }
}

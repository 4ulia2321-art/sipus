<?php

namespace Tests\Feature;

use App\Models\TUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PustakawanTotalTransaksiTest extends TestCase
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
            'kelas'      => 'VII A',
        ]);
    }

    private function buatBuku(string $judul, int $stok = 5): int
    {
        $idKategori = DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori uji',
        ]);

        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'TRX-' . uniqid(),
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

    private function buatPeminjaman(int $idSiswa, array $idBuku, string $status = 'Dipinjam', ?string $jatuhTempo = null): int
    {
        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa'            => $idSiswa,
            'tanggal_pinjam'      => Carbon::today()->subDays(10)->toDateString(),
            'tanggal_jatuh_tempo' => $jatuhTempo ?: Carbon::today()->subDays(3)->toDateString(),
            'status'              => $status,
        ]);

        foreach ($idBuku as $id) {
            DB::table('detail_peminjaman')->insert([
                'id_peminjaman' => $idPeminjaman,
                'id_buku'       => $id,
                'jumlah'        => 1,
            ]);
        }

        return $idPeminjaman;
    }

    public function test_total_keterlambatan_sama_dengan_dashboard_dan_tidak_terlipat_detail_buku(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawantotal');
        $siswaUser = $this->buatUser('Siswa', 'siswatotal');
        $idSiswa = $this->buatSiswa($siswaUser);

        $bukuA = $this->buatBuku('Buku Terlambat A');
        $bukuB = $this->buatBuku('Buku Terlambat B');
        $bukuC = $this->buatBuku('Buku Terlambat C');

        // Dua transaksi terlambat; satu transaksi memuat dua buku.
        $this->buatPeminjaman($idSiswa, [$bukuA, $bukuB]);
        $this->buatPeminjaman($idSiswa, [$bukuC]);

        // Peminjaman yang belum jatuh tempo tidak boleh ikut terhitung.
        $this->buatPeminjaman(
            $idSiswa,
            [$this->buatBuku('Buku Masih Aktif')],
            'Dipinjam',
            Carbon::today()->addDays(3)->toDateString()
        );

        $this->actingAs($pustakawan)
            ->get('/pustakawan')
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                return collect($stats)->firstWhere('label', 'Terlambat')['value'] === 2;
            });

        $this->actingAs($pustakawan)
            ->get('/pustakawan/keterlambatan')
            ->assertOk()
            ->assertViewHas('peminjaman', function ($paginasi): bool {
                return $paginasi->total() === 2;
            })
            ->assertViewHas('peminjaman', function ($paginasi): bool {
                $judul = $paginasi->getCollection()->pluck('judul')->implode(' | ');

                return str_contains($judul, 'Buku Terlambat A')
                    && str_contains($judul, 'Buku Terlambat B');
            });

        // Tiga transaksi aktif, bukan lima baris detail buku.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman')
            ->assertOk()
            ->assertViewHas('peminjaman', fn ($paginasi): bool => $paginasi->total() === 3);
    }

    public function test_riwayat_pengembalian_menghitung_transaksi_bukan_detail_buku(): void
    {
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawankembali');
        $siswaUser = $this->buatUser('Siswa', 'siswakembali');
        $idSiswa = $this->buatSiswa($siswaUser, '3002');

        $idPinjamA = $this->buatPeminjaman(
            $idSiswa,
            [$this->buatBuku('Buku Kembali A'), $this->buatBuku('Buku Kembali B')],
            'Dikembalikan'
        );
        $idPinjamB = $this->buatPeminjaman(
            $idSiswa,
            [$this->buatBuku('Buku Kembali C')],
            'Dikembalikan'
        );

        foreach ([$idPinjamA, $idPinjamB] as $id) {
            DB::table('pengembalian')->insert([
                'id_peminjaman' => $id,
                'tanggal_kembali' => Carbon::today()->toDateString(),
                'denda'         => 0,
                'kategori'      => 'Tepat Waktu',
            ]);
        }

        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengembalian?tab=riwayat')
            ->assertOk()
            ->assertViewHas('pengembalian', function ($paginasi): bool {
                return $paginasi->total() === 2;
            });

        // Riwayat peminjaman juga dihitung per transaksi, bukan per buku.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/peminjaman?tab=riwayat')
            ->assertOk()
            ->assertViewHas('peminjaman', fn ($paginasi): bool => $paginasi->total() === 2);
    }
}

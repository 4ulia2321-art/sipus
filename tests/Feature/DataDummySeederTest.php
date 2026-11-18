<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DataDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataDummySeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDummy(): void
    {
        Storage::fake('public');
        $this->seed(DataDummySeeder::class);
    }

    public function test_seeder_membuat_data_dummy_lengkap_dan_konsisten(): void
    {
        $this->seedDummy();

        $siswaIds = DB::table('siswa')->where('nis', 'like', '20255%')->pluck('id_siswa');
        $bukuIds = DB::table('buku')->where('no_seri_buku', 'like', 'DUM-%')->pluck('id_buku');
        $peminjamanIds = DB::table('peminjaman')->whereIn('id_siswa', $siswaIds)->pluck('id_peminjaman');

        $this->assertCount(30, $siswaIds);
        $this->assertCount(30, $bukuIds);
        $this->assertSame(9, DB::table('kategori_buku')->count());
        $this->assertCount(30, $peminjamanIds);
        $this->assertSame(40, DB::table('detail_peminjaman')->whereIn('id_peminjaman', $peminjamanIds)->count());
        $this->assertSame(13, DB::table('pengembalian')->whereIn('id_peminjaman', $peminjamanIds)->count());

        $status = DB::table('peminjaman')
            ->whereIn('id_peminjaman', $peminjamanIds)
            ->select('status', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();

        $this->assertSame(8, $status['Dikembalikan']);
        $this->assertSame(5, $status['Menunggu Pembayaran']);
        $this->assertSame(4, $status['Ditolak']);
        $this->assertSame(8, $status['Dipinjam']);
        $this->assertSame(5, $status['Menunggu']);

        // Semua siswa dan buku dummy terpakai dalam transaksi.
        $this->assertCount(0, $siswaIds->diff(DB::table('peminjaman')->distinct()->pluck('id_siswa')));
        $this->assertCount(0, $bukuIds->diff(DB::table('detail_peminjaman')->distinct()->pluck('id_buku')));

        // Setiap transaksi punya detail dan stok tidak negatif.
        $this->assertCount(0, $peminjamanIds->diff(DB::table('detail_peminjaman')->distinct()->pluck('id_peminjaman')));
        $this->assertSame(0, DB::table('buku')->where('stok', '<', 0)->count());

        // Pengembalian hanya ada pada status yang memang sudah selesai.
        $tidakKonsisten = DB::table('peminjaman as p')
            ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
            ->whereIn('p.id_peminjaman', $peminjamanIds)
            ->where(function ($query) {
                $query->where(function ($w) {
                    $w->whereIn('p.status', ['Dikembalikan', 'Menunggu Pembayaran'])
                        ->whereNull('pg.id_peminjaman');
                })->orWhere(function ($w) {
                    $w->whereNotIn('p.status', ['Dikembalikan', 'Menunggu Pembayaran'])
                        ->whereNotNull('pg.id_peminjaman');
                });
            })
            ->count();

        $this->assertSame(0, $tidakKonsisten);

        // Transaksi dummy tersebar pada beberapa bulan dan tahun.
        $bulan = DB::table('peminjaman')
            ->whereIn('id_peminjaman', $peminjamanIds)
            ->selectRaw('DISTINCT SUBSTR(tanggal_pinjam, 1, 7) as periode')
            ->get();

        $this->assertGreaterThanOrEqual(6, $bulan->count());
    }

    public function test_seeder_aman_dijalankan_ulang_tanpa_duplikat(): void
    {
        $this->seedDummy();
        $this->seedDummy();

        $this->assertSame(30, DB::table('siswa')->where('nis', 'like', '20255%')->count());
        $this->assertSame(30, DB::table('buku')->where('no_seri_buku', 'like', 'DUM-%')->count());
        $this->assertSame(9, DB::table('kategori_buku')->count());

        $siswaIds = DB::table('siswa')->where('nis', 'like', '20255%')->pluck('id_siswa');
        $this->assertSame(30, DB::table('peminjaman')->whereIn('id_siswa', $siswaIds)->count());
    }

    public function test_data_dummy_tampil_pada_halaman_admin_pustakawan_dan_siswa(): void
    {
        $this->seedDummy();

        $admin = User::factory()->admin()->create();
        $pustakawan = User::factory()->pustakawan()->create();
        $siswa = User::where('username', '20255001@siswa.sipus.sch.id')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/buku?q=Petualangan+di+Pulau+Harapan')
            ->assertOk()
            ->assertSee('Petualangan di Pulau Harapan');

        $this->actingAs($admin)
            ->get('/admin/anggota?q=Alya+Rahmawati')
            ->assertOk()
            ->assertSee('Alya Rahmawati');

        $this->actingAs($admin)
            ->get('/admin/kategori?q=Sains')
            ->assertOk()
            ->assertSee('Sains');

        $this->actingAs($pustakawan)
            ->get('/pustakawan/buku?q=Ensiklopedia+Sains+untuk+Pelajar')
            ->assertOk()
            ->assertSee('Ensiklopedia Sains untuk Pelajar');

        // Lima pengajuan terbaru berstatus Menunggu berasal dari siswa dummy.
        $this->actingAs($pustakawan)
            ->get('/pustakawan/pengajuan')
            ->assertOk()
            ->assertSee('Zaki Mubarak');

        $this->actingAs($siswa)
            ->get('/pengajuan')
            ->assertOk()
            ->assertSee('Matematika Terapan Kelas VII');

        $this->actingAs($siswa)
            ->get('/riwayat')
            ->assertOk()
            ->assertSee('Matematika Terapan Kelas VII');
    }
}

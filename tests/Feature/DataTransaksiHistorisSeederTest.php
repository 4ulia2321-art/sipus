<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Database\Seeders\DataDummySeeder;
use Database\Seeders\DataTransaksiHistorisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataTransaksiHistorisSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDummy(): void
    {
        Storage::fake('public');
        $this->seed(DataDummySeeder::class);
    }

    public function test_seeder_menambah_transaksi_historis_satu_per_bulan_selama_24_bulan(): void
    {
        $this->seedDummy();

        $idSebelum = DB::table('peminjaman')->pluck('id_peminjaman')->all();
        $stokSebelum = DB::table('buku')->orderBy('id_buku')->pluck('stok', 'id_buku')->all();
        $transaksiLama = DB::table('peminjaman')
            ->whereIn('id_peminjaman', $idSebelum)
            ->orderBy('id_peminjaman')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $this->seed(DataTransaksiHistorisSeeder::class);

        $baru = DB::table('peminjaman')
            ->whereNotIn('id_peminjaman', $idSebelum)
            ->orderBy('tanggal_pinjam')
            ->get();

        $this->assertCount(24, $baru);
        $this->assertCount(
            24,
            $baru->pluck('tanggal_pinjam')->map(fn ($tanggal) => substr($tanggal, 0, 7))->unique()
        );
        $this->assertGreaterThanOrEqual(
            2,
            $baru->pluck('tanggal_pinjam')->map(fn ($tanggal) => substr($tanggal, 0, 4))->unique()->count()
        );

        $lamaPinjam = max(1, Pengaturan::int('lama_pinjam_hari', 7));
        $dendaPerHari = max(0, Pengaturan::int('denda_per_hari', 500));
        $jumlahStatus = [];
        $tepatWaktu = 0;
        $terlambat = 0;

        foreach ($baru as $row) {
            $kembali = DB::table('pengembalian')
                ->where('id_peminjaman', $row->id_peminjaman)
                ->first();

            $this->assertNotNull($kembali);

            $pinjam = Carbon::parse($row->tanggal_pinjam);
            $jatuhTempo = Carbon::parse($row->tanggal_jatuh_tempo);
            $tanggalKembali = Carbon::parse($kembali->tanggal_kembali);

            $this->assertSame($lamaPinjam, (int) $pinjam->diffInDays($jatuhTempo));
            $this->assertTrue($tanggalKembali->gte($pinjam));

            $telat = $tanggalKembali->gt($jatuhTempo)
                ? (int) $jatuhTempo->diffInDays($tanggalKembali)
                : 0;

            $this->assertSame((float) ($telat * $dendaPerHari), (float) $kembali->denda);
            $this->assertSame($telat > 0 ? 'Terlambat' : 'Tepat Waktu', $kembali->kategori);
            $this->assertSame(
                $row->status === 'Menunggu Pembayaran' ? 'Belum Lunas' : 'Lunas',
                $kembali->status_bayar
            );
            $this->assertContains($row->status, ['Dikembalikan', 'Menunggu Pembayaran']);

            $jumlahStatus[$row->status] = ($jumlahStatus[$row->status] ?? 0) + 1;

            if ($telat > 0) {
                $terlambat++;
            } else {
                $tepatWaktu++;
            }
        }

        $this->assertSame(20, $jumlahStatus['Dikembalikan'] ?? 0);
        $this->assertSame(4, $jumlahStatus['Menunggu Pembayaran'] ?? 0);
        $this->assertGreaterThan(0, $tepatWaktu);
        $this->assertGreaterThan(0, $terlambat);

        // Data lama dan stok buku tidak berubah oleh transaksi selesai.
        $transaksiSesudah = DB::table('peminjaman')
            ->whereIn('id_peminjaman', $idSebelum)
            ->orderBy('id_peminjaman')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $this->assertSame($transaksiLama, $transaksiSesudah);
        $this->assertSame(
            $stokSebelum,
            DB::table('buku')->orderBy('id_buku')->pluck('stok', 'id_buku')->all()
        );

        // Setiap transaksi historis memiliki detail peminjaman.
        $tanpaDetail = $baru->pluck('id_peminjaman')
            ->diff(DB::table('detail_peminjaman')->distinct()->pluck('id_peminjaman'));

        $this->assertCount(0, $tanpaDetail);
    }

    public function test_seeder_historis_idempoten_dan_tidak_membuat_duplikat(): void
    {
        $this->seedDummy();

        $this->seed(DataTransaksiHistorisSeeder::class);
        $jumlahPeminjaman = DB::table('peminjaman')->count();
        $jumlahPengembalian = DB::table('pengembalian')->count();

        $this->seed(DataTransaksiHistorisSeeder::class);

        $this->assertSame($jumlahPeminjaman, DB::table('peminjaman')->count());
        $this->assertSame($jumlahPengembalian, DB::table('pengembalian')->count());
        $this->assertSame(
            $jumlahPeminjaman,
            DB::table('detail_peminjaman')->distinct()->count('id_peminjaman')
        );

        $duplikat = DB::table('peminjaman')
            ->select('id_siswa', 'tanggal_pinjam', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('id_siswa', 'tanggal_pinjam')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertCount(0, $duplikat);
    }

    public function test_laporan_admin_bisa_menampilkan_seluruh_periode_dan_filter_bulan_tahun(): void
    {
        $this->seedDummy();
        $this->seed(DataTransaksiHistorisSeeder::class);

        $admin = User::factory()->admin()->create();
        $totalPeminjaman = DB::table('peminjaman')->count();

        // Tanpa parameter: seluruh periode dan seluruh tahun tampil.
        $semua = $this->actingAs($admin)->get('/admin/laporan');

        $semua->assertOk()
            ->assertSee('Mode Periode')
            ->assertSee('name="bulan"', false)
            ->assertSee('name="tahun"', false)
            ->assertSee('Terapkan');
        $this->assertSame('semua', $semua->viewData('mode'));
        $this->assertSame($totalPeminjaman, $semua->viewData('stat')['peminjaman']);
        $this->assertStringContainsString('Seluruh Periode', $semua->viewData('periodeLabel'));
        $this->assertGreaterThanOrEqual(2, count($semua->viewData('kolomJudul')));

        $tahun = (int) Carbon::today()->format('Y');
        $bulan = (int) Carbon::today()->format('n');

        // Filter bulan dan tahun tertentu.
        $filter = $this->actingAs($admin)->get('/admin/laporan?'.http_build_query([
            'mode' => 'filter',
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]));

        $filter->assertOk();
        $this->assertSame('filter', $filter->viewData('mode'));

        $harapan = DB::table('peminjaman')
            ->whereYear('tanggal_pinjam', $tahun)
            ->whereMonth('tanggal_pinjam', $bulan)
            ->count();

        $this->assertGreaterThan(0, $harapan);
        $this->assertSame($harapan, $filter->viewData('stat')['peminjaman']);

        $kunciPeriode = $tahun.'-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
        foreach ($filter->viewData('rekapJudul') as $row) {
            $this->assertSame([$kunciPeriode], array_keys($row['periode']));
        }

        // Filter yang sama berlaku pada tab pengembalian dan transaksi.
        $pengembalian = $this->actingAs($admin)->get('/admin/laporan?'.http_build_query([
            'mode' => 'filter',
            'bulan' => $bulan,
            'tahun' => $tahun,
            'tab' => 'pengembalian',
        ]));

        $pengembalian->assertOk();
        $this->assertSame(
            DB::table('pengembalian')
                ->whereYear('tanggal_kembali', $tahun)
                ->whereMonth('tanggal_kembali', $bulan)
                ->count(),
            $pengembalian->viewData('rows')->total()
        );

        $transaksi = $this->actingAs($admin)->get('/admin/laporan?'.http_build_query([
            'mode' => 'filter',
            'bulan' => $bulan,
            'tahun' => $tahun,
            'tab' => 'transaksi',
        ]));

        $transaksi->assertOk();
        $this->assertSame($harapan, $transaksi->viewData('rows')->total());

        $terlambat = $this->actingAs($admin)->get('/admin/laporan?'.http_build_query([
            'mode' => 'filter',
            'bulan' => $bulan,
            'tahun' => $tahun,
            'tab' => 'keterlambatan',
        ]));

        $terlambat->assertOk();

        // Filter tahun saja menghitung seluruh bulan pada tahun itu.
        $tahunSaja = $this->actingAs($admin)->get('/admin/laporan?mode=filter&tahun='.$tahun);

        $tahunSaja->assertOk();
        $this->assertSame(
            DB::table('peminjaman')->whereYear('tanggal_pinjam', $tahun)->count(),
            $tahunSaja->viewData('stat')['peminjaman']
        );

        // Filter bulan saja menghitung bulan tersebut pada semua tahun.
        $bulanSaja = $this->actingAs($admin)->get('/admin/laporan?mode=filter&bulan='.$bulan);

        $bulanSaja->assertOk();
        $this->assertSame(
            DB::table('peminjaman')->whereMonth('tanggal_pinjam', $bulan)->count(),
            $bulanSaja->viewData('stat')['peminjaman']
        );

        // URL lama dengan rentang tanggal tetap berjalan sebagai mode kustom.
        $kustom = $this->actingAs($admin)
            ->get('/admin/laporan?mulai=2026-10-01&selesai=2026-10-31');

        $kustom->assertOk();
        $this->assertSame('kustom', $kustom->viewData('mode'));
        $this->assertSame('2026-10-01', $kustom->viewData('mulai')->format('Y-m-d'));

        // Mode filter tanpa bulan/tahun kembali ke seluruh periode.
        $kosong = $this->actingAs($admin)->get('/admin/laporan?mode=filter');

        $kosong->assertOk();
        $this->assertSame('semua', $kosong->viewData('mode'));
    }
}

<?php

namespace Database\Seeders;

use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan transaksi contoh selesai satu per bulan selama 24 bulan
 * terakhir agar laporan Admin memiliki sebaran bulan dan tahun.
 *
 * Seeder ini hanya menambah data dan tidak mengubah transaksi lama.
 * Tanggal pinjam, jatuh tempo, tanggal kembali, serta denda dihitung
 * dari pengaturan aplikasi sehingga tetap konsisten. Seeder idempoten
 * karena setiap transaksi diperiksa dari kombinasi siswa dan tanggal
 * pinjam yang dibentuk secara deterministik dari tahun dan bulan.
 */
class DataTransaksiHistorisSeeder extends Seeder
{
    private const JUMLAH_BULAN = 24;

    public function run(): void
    {
        // Pastikan siswa dan buku dummy tersedia. Seeder ini idempoten,
        // jadi aman dipanggil walaupun data dummy sudah pernah dibuat.
        $this->call(DataDummySeeder::class);

        $siswaIds = DB::table('siswa')
            ->where('nis', 'like', '20255%')
            ->orderBy('nis')
            ->pluck('id_siswa')
            ->map(fn ($id) => (int) $id)
            ->all();

        $bukuIds = DB::table('buku')
            ->where('no_seri_buku', 'like', 'DUM-%')
            ->orderBy('no_seri_buku')
            ->pluck('id_buku')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($siswaIds === [] || $bukuIds === []) {
            $this->command?->warn(
                'Siswa atau buku dummy belum tersedia. '
                .'Transaksi historis tidak dibuat.'
            );

            return;
        }

        $lamaPinjam = max(1, Pengaturan::int('lama_pinjam_hari', 7));
        $dendaPerHari = max(0, Pengaturan::int('denda_per_hari', 500));

        $awal = Carbon::today()->startOfMonth()->subMonths(self::JUMLAH_BULAN);
        $dibuat = 0;
        $dilewati = 0;

        DB::transaction(function () use (
            $awal,
            $siswaIds,
            $bukuIds,
            $lamaPinjam,
            $dendaPerHari,
            &$dibuat,
            &$dilewati
        ) {
            for ($i = 0; $i < self::JUMLAH_BULAN; $i++) {
                $bulan = $awal->copy()->addMonths($i);

                if ($this->buatTransaksi(
                    $bulan,
                    $siswaIds,
                    $bukuIds,
                    $lamaPinjam,
                    $dendaPerHari
                )) {
                    $dibuat++;
                } else {
                    $dilewati++;
                }
            }
        });

        $this->command?->info(
            'Transaksi historis: '.$dibuat.' ditambahkan, '
            .$dilewati.' sudah ada dan dilewati.'
        );
    }

    /**
     * Membuat satu transaksi selesai pada bulan tertentu.
     *
     * @param  array<int, int>  $siswaIds
     * @param  array<int, int>  $bukuIds
     * @return bool true jika transaksi baru dibuat.
     */
    private function buatTransaksi(
        Carbon $bulan,
        array $siswaIds,
        array $bukuIds,
        int $lamaPinjam,
        int $dendaPerHari
    ): bool {
        $kode = ($bulan->year * 12) + $bulan->month;

        // Hari pinjam 3..10 agar pengembalian tetap jatuh pada bulan yang
        // sama meskipun transaksi terlambat beberapa hari.
        $tanggalPinjam = $bulan->copy()->day(3 + ($kode % 8));
        $jatuhTempo = $tanggalPinjam->copy()->addDays($lamaPinjam);

        $sisa = $kode % 6;
        $telat = match ($sisa) {
            0 => 4,
            2, 4 => 2,
            default => 0,
        };
        $belumLunas = $sisa === 0;
        $tanggalKembali = $jatuhTempo->copy()->addDays($telat);

        // Enam siswa dummy terakhir hanya memiliki transaksi dummy di
        // sekitar hari ini. Karena tanggal historis selalu berada pada
        // tanggal 3-10 bulan lampau, kombinasi siswa dan tanggal tidak
        // bertabrakan dengan transaksi dummy yang sudah ada.
        $siswaHistoris = array_slice($siswaIds, -6);
        $idSiswa = $siswaHistoris[$kode % count($siswaHistoris)];
        $idBuku = $bukuIds[(($bulan->year * 7) + $bulan->month) % count($bukuIds)];

        $sudahAda = DB::table('peminjaman')
            ->where('id_siswa', $idSiswa)
            ->where('tanggal_pinjam', $tanggalPinjam->toDateString())
            ->exists();

        if ($sudahAda) {
            return false;
        }

        $idPeminjaman = DB::table('peminjaman')->insertGetId([
            'id_siswa' => $idSiswa,
            'tanggal_pinjam' => $tanggalPinjam->toDateString(),
            'tanggal_jatuh_tempo' => $jatuhTempo->toDateString(),
            'status' => $belumLunas ? 'Menunggu Pembayaran' : 'Dikembalikan',
            'alasan_penolakan' => null,
            'pengajuan_kembali_at' => null,
            'perpanjangan_diminta_at' => null,
            'perpanjangan_status' => null,
        ]);

        DB::table('detail_peminjaman')->insert([
            'id_peminjaman' => $idPeminjaman,
            'id_buku' => $idBuku,
            'jumlah' => $kode % 3 === 0 ? 2 : 1,
        ]);

        DB::table('pengembalian')->insert([
            'id_peminjaman' => $idPeminjaman,
            'tanggal_kembali' => $tanggalKembali->toDateString(),
            'denda' => $telat * $dendaPerHari,
            'kategori' => $telat > 0 ? 'Terlambat' : 'Tepat Waktu',
            'status_bayar' => $belumLunas ? 'Belum Lunas' : 'Lunas',
        ]);

        return true;
    }
}

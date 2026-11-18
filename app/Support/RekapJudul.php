<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun rekap peminjaman per judul buku beserta rincian bulanan.
 *
 * Pengelompokan dilakukan di PHP agar query tetap kompatibel dengan MySQL
 * (aplikasi) dan SQLite (pengujian) tanpa fungsi tanggal khusus database.
 * Kolom tahun dan bulan dibentuk otomatis dari tanggal peminjaman yang ada.
 */
class RekapJudul
{
    /**
     * Singkatan bulan agar label tidak bergantung pada locale aplikasi.
     */
    private const BULAN = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    /**
     * @param  Collection<int, object>  $baris  Baris dengan properti id_peminjaman, judul, tanggal_pinjam, dan jumlah.
     * @return array{
     *     0: Collection<int, array{judul:string,total_transaksi:int,total_buku:int,periode:array<string,int>}>,
     *     1: array<int, array{tahun:string,bulan:array<int, array{key:string,label:string}>}>
     * }
     */
    public static function susun(Collection $baris): array
    {
        $buku = [];
        $periode = [];

        foreach ($baris as $row) {
            $judul = trim((string) ($row->judul ?? '')) ?: '-';
            $tanggal = Carbon::parse($row->tanggal_pinjam);
            $kunci = $tanggal->format('Y-m');
            $jumlah = (int) ($row->jumlah ?? 0);
            $idTransaksi = (string) $row->id_peminjaman;

            if (! isset($buku[$judul])) {
                $buku[$judul] = [
                    'judul' => $judul,
                    'total_buku' => 0,
                    'transaksi' => [],
                    'periode' => [],
                ];
            }

            $buku[$judul]['total_buku'] += $jumlah;
            $buku[$judul]['transaksi'][$idTransaksi] = true;
            $buku[$judul]['periode'][$kunci] = ($buku[$judul]['periode'][$kunci] ?? 0) + $jumlah;

            $periode[$kunci] = [
                'key' => $kunci,
                'tahun' => $tanggal->format('Y'),
                'bulan' => $tanggal->month,
                'label' => self::BULAN[$tanggal->month],
            ];
        }

        $daftar = collect($buku)
            ->map(function (array $row): array {
                $row['total_transaksi'] = count($row['transaksi']);
                unset($row['transaksi']);

                return $row;
            })
            ->sortBy([
                ['total_buku', 'desc'],
                ['judul', 'asc'],
            ])
            ->values();

        // Tahun terbaru lebih dulu, lalu bulan terbaru di dalam setiap tahun.
        $kolom = collect($periode)
            ->sortByDesc('key')
            ->groupBy('tahun')
            ->map(fn (Collection $items, $tahun) => [
                'tahun' => (string) $tahun,
                'bulan' => $items->sortByDesc('bulan')->values()->all(),
            ])
            ->values()
            ->all();

        return [$daftar, $kolom];
    }
}

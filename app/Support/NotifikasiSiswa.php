<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Notifikasi peminjaman yang relevan untuk siswa:
 * buku yang sudah jatuh tempo (terlambat) atau akan jatuh tempo.
 *
 * Estimasi denda memakai ketentuan yang sama dengan proses pengembalian:
 * jumlah hari terlambat x denda_per_hari.
 */
class NotifikasiSiswa
{
    /**
     * @return Collection<int, array{
     *     jenis: string,
     *     judul: string,
     *     pesan: string,
     *     waktu: Carbon,
     *     url: string,
     *     id_peminjaman: int,
     *     tanggal_jatuh_tempo: Carbon,
     *     hari_terlambat: int,
     *     sisa_hari: int,
     *     denda: int,
     *     keterangan_denda: string|null,
     *     menunggu_pengembalian: bool
     * }>
     */
    public static function untukSiswa(int $idSiswa, int $batasHari = 3): Collection
    {
        if ($idSiswa <= 0) {
            return collect();
        }

        Carbon::setLocale('id');

        $hariIni = Carbon::today();
        $dendaPerHari = Pengaturan::int('denda_per_hari', 500);

        $peminjaman = DB::table('peminjaman as p')
            ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
            ->where('p.id_siswa', $idSiswa)
            ->whereIn('p.status', ['Dipinjam', 'Terlambat'])
            ->whereNull('pg.id_peminjaman')
            ->whereNotNull('p.tanggal_jatuh_tempo')
            ->whereDate('p.tanggal_jatuh_tempo', '<=', $hariIni->copy()->addDays($batasHari)->toDateString())
            ->orderBy('p.tanggal_jatuh_tempo')
            ->get(['p.id_peminjaman', 'p.tanggal_jatuh_tempo', 'p.pengajuan_kembali_at']);

        if ($peminjaman->isEmpty()) {
            return collect();
        }

        $judulBuku = DB::table('detail_peminjaman as dp')
            ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
            ->whereIn('dp.id_peminjaman', $peminjaman->pluck('id_peminjaman')->all())
            ->orderBy('b.judul')
            ->get(['dp.id_peminjaman', 'b.judul'])
            ->groupBy('id_peminjaman')
            ->map(fn ($baris) => $baris->pluck('judul')->implode(', '));

        return $peminjaman->map(function ($baris) use ($hariIni, $dendaPerHari, $judulBuku) {
            $jatuhTempo = Carbon::parse($baris->tanggal_jatuh_tempo)->startOfDay();
            $terlambat = $jatuhTempo->lt($hariIni)
                ? (int) $jatuhTempo->diffInDays($hariIni)
                : 0;
            $sisaHari = ! $jatuhTempo->lt($hariIni)
                ? (int) $hariIni->diffInDays($jatuhTempo)
                : 0;
            $denda = $terlambat * $dendaPerHari;
            $daftarJudul = $judulBuku[$baris->id_peminjaman] ?? 'Buku';
            $menungguPengembalian = ! empty($baris->pengajuan_kembali_at);

            if ($terlambat > 0) {
                $jenis = 'terlambat';
                $judul = 'Terlambat ' . $terlambat . ' Hari';
                $pesan = 'Buku: ' . $daftarJudul . '. Sudah melewati jatuh tempo '
                    . $jatuhTempo->translatedFormat('d F Y') . '.';
                $pesan .= $menungguPengembalian
                    ? ' Pengajuan pengembalian sudah dikirim, tetapi denda tetap dihitung sampai pustakawan mencatat pengembalian.'
                    : ' Segera kembalikan buku.';
            } elseif ($sisaHari === 0) {
                $jenis = 'jatuh_tempo';
                $judul = 'Jatuh Tempo Hari Ini';
                $pesan = 'Buku: ' . $daftarJudul . '. Batas pengembalian hari ini, '
                    . $jatuhTempo->translatedFormat('d F Y')
                    . '. Kembalikan hari ini agar tidak dikenakan denda.';
            } else {
                $jenis = 'jatuh_tempo';
                $judul = 'Akan Jatuh Tempo';
                $pesan = 'Buku: ' . $daftarJudul . '. Akan jatuh tempo dalam '
                    . $sisaHari . ' hari (' . $jatuhTempo->translatedFormat('d F Y') . ').';
            }

            return [
                'jenis'                 => $jenis,
                'judul'                 => $judul,
                'pesan'                 => $pesan,
                'waktu'                 => Carbon::today(),
                'url'                   => route('riwayat.show', $baris->id_peminjaman),
                'id_peminjaman'         => (int) $baris->id_peminjaman,
                'tanggal_jatuh_tempo'   => $jatuhTempo,
                'hari_terlambat'        => $terlambat,
                'sisa_hari'             => $sisaHari,
                'denda'                 => $denda,
                'keterangan_denda'      => $terlambat > 0
                    ? 'Estimasi denda: Rp ' . number_format($denda, 0, ',', '.')
                    : null,
                'menunggu_pengembalian' => $menungguPengembalian,
            ];
        })->values();
    }
}

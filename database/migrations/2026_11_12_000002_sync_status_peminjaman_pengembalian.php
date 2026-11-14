<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan status peminjaman yang sudah memiliki data pengembalian.
 *
 * Data lama dapat memiliki status "Dipinjam" atau "Dikembalikan" yang tidak
 * sesuai dengan status pelunasan dendanya. Migrasi ini tidak menghapus data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengembalian') || ! Schema::hasTable('peminjaman')) {
            return;
        }

        $baris = DB::table('pengembalian as pg')
            ->join('peminjaman as p', 'p.id_peminjaman', '=', 'pg.id_peminjaman')
            ->select('pg.id_peminjaman', 'pg.denda', 'pg.status_bayar', 'p.status')
            ->get();

        foreach ($baris as $row) {
            $statusBaru = ((float) $row->denda <= 0 || $row->status_bayar === 'Lunas')
                ? 'Dikembalikan'
                : 'Menunggu Pembayaran';

            if ($row->status === $statusBaru) {
                continue;
            }

            // Hanya status pengembalian yang diselaraskan; status pengajuan
            // (Menunggu/Ditolak) tidak diubah.
            if (! in_array($row->status, ['Dipinjam', 'Terlambat', 'Dikembalikan', 'Menunggu Pembayaran'], true)) {
                continue;
            }

            DB::table('peminjaman')
                ->where('id_peminjaman', $row->id_peminjaman)
                ->update(['status' => $statusBaru]);
        }
    }

    public function down(): void
    {
        // Perbaikan data tidak dibalik agar status historis tetap konsisten.
    }
};

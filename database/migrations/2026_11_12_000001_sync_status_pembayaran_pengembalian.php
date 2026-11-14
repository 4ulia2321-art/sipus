<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan data lama dengan aturan baru:
 * - pengembalian tanpa denda otomatis berstatus Lunas;
 * - pengembalian berdenda yang belum lunas belum dianggap selesai.
 *
 * Migrasi ini hanya memperbarui nilai status, tidak menghapus data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengembalian') || ! Schema::hasTable('peminjaman')) {
            return;
        }

        DB::table('pengembalian')
            ->where('denda', '<=', 0)
            ->update(['status_bayar' => 'Lunas']);

        $idBelumLunas = DB::table('pengembalian')
            ->where('denda', '>', 0)
            ->where(function ($query) {
                $query->whereNull('status_bayar')
                    ->orWhere('status_bayar', '!=', 'Lunas');
            })
            ->pluck('id_peminjaman');

        if ($idBelumLunas->isEmpty()) {
            return;
        }

        DB::table('peminjaman')
            ->whereIn('id_peminjaman', $idBelumLunas)
            ->where('status', 'Dikembalikan')
            ->update(['status' => 'Menunggu Pembayaran']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('peminjaman')) {
            return;
        }

        DB::table('peminjaman')
            ->where('status', 'Menunggu Pembayaran')
            ->update(['status' => 'Dikembalikan']);
    }
};

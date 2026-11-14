<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai peminjaman yang sudah diajukan pengembalian oleh siswa.
     *
     * Status utama peminjaman tetap "Dipinjam" sampai pustakawan mencatat
     * pengembalian. Dengan begitu perhitungan stok, statistik, dan alur
     * peminjaman yang sudah ada tidak berubah.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('peminjaman', 'pengajuan_kembali_at')) {
            Schema::table('peminjaman', function (Blueprint $table) {
                $table->timestamp('pengajuan_kembali_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('peminjaman', 'pengajuan_kembali_at')) {
            Schema::table('peminjaman', function (Blueprint $table) {
                $table->dropColumn('pengajuan_kembali_at');
            });
        }
    }
};

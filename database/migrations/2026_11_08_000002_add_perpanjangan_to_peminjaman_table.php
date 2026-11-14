<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda pengajuan perpanjangan dari siswa.
     *
     * Status utama peminjaman tetap "Dipinjam" sampai Pustakawan
     * memverifikasi pengajuan. Setelah disetujui, tanggal jatuh tempo
     * diperpanjang sesuai lama pinjam pada pengaturan.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('peminjaman', 'perpanjangan_diminta_at')) {
            Schema::table('peminjaman', function (Blueprint $table) {
                $table->timestamp('perpanjangan_diminta_at')->nullable()->after('pengajuan_kembali_at');
            });
        }

        if (! Schema::hasColumn('peminjaman', 'perpanjangan_status')) {
            Schema::table('peminjaman', function (Blueprint $table) {
                $table->string('perpanjangan_status', 20)->nullable()->after('perpanjangan_diminta_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('peminjaman', 'perpanjangan_status')) {
            Schema::table('peminjaman', function (Blueprint $table) {
                $table->dropColumn('perpanjangan_status');
            });
        }

        if (Schema::hasColumn('peminjaman', 'perpanjangan_diminta_at')) {
            Schema::table('peminjaman', function (Blueprint $table) {
                $table->dropColumn('perpanjangan_diminta_at');
            });
        }
    }
};

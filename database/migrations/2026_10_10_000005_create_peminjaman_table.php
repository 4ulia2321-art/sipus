<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peminjaman', function (Blueprint $table) {
            $table->integer('id_peminjaman', true);
            $table->integer('id_siswa');
            $table->date('tanggal_pinjam');
            $table->date('tanggal_jatuh_tempo');
            $table->string('status', 30);

            $table->index('id_siswa');
            $table->foreign('id_siswa', 'fk_peminjaman_siswa')
                ->references('id_siswa')
                ->on('siswa')
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman');
    }
};
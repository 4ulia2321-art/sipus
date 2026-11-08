<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengembalian', function (Blueprint $table) {
            $table->integer('id_pengembalian', true);
            $table->integer('id_peminjaman');
            $table->date('tanggal_kembali');
            $table->decimal('denda', 10, 2)->default(0);
            $table->string('kategori', 50)->nullable();

            $table->index('id_peminjaman');
            $table->foreign('id_peminjaman', 'fk_pengembalian_peminjaman')
                ->references('id_peminjaman')
                ->on('peminjaman')
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalian');
    }
};
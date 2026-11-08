<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_peminjaman', function (Blueprint $table) {
            $table->integer('id_detail', true);
            $table->integer('id_peminjaman');
            $table->integer('id_buku');
            $table->integer('jumlah');

            $table->index('id_peminjaman');
            $table->index('id_buku');
            $table->foreign('id_peminjaman', 'fk_detail_peminjaman')
                ->references('id_peminjaman')
                ->on('peminjaman')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('id_buku', 'fk_detail_buku')
                ->references('id_buku')
                ->on('buku')
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_peminjaman');
    }
};
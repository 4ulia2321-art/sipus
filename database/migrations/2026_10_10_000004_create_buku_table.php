<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku', function (Blueprint $table) {
            $table->integer('id_buku', true);
            $table->integer('id_kategori');
            $table->string('no_seri_buku', 30);
            $table->string('judul', 200);
            $table->string('pengarang', 100)->nullable();
            $table->string('penerbit', 100)->nullable();
            $table->year('tahun_terbit')->nullable();
            $table->string('kategori', 100)->nullable();
            $table->string('lokasi_rak', 50)->nullable();
            $table->integer('stok')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->index('id_kategori');
            $table->foreign('id_kategori', 'fk_buku_kategori')
                ->references('id_kategori')
                ->on('kategori_buku')
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku');
    }
};
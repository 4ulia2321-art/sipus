<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->integer('id_siswa', true);
            $table->integer('id_user');
            $table->string('nis', 20);
            $table->string('nama_siswa', 100);
            $table->string('kelas', 30);
            $table->text('alamat')->nullable();
            $table->string('no_telp', 20)->nullable();

            $table->index('id_user');
            $table->foreign('id_user', 'siswa_ibfk_1')
                ->references('id_user')
                ->on('t_user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sebagian database production sudah memiliki t_user dari proses
        // impor data sebelum migrasi dijalankan. Jangan buat ulang tabel
        // tersebut agar 1.024 akun yang sudah ada tetap aman.
        if (Schema::hasTable('t_user')) {
            return;
        }

        Schema::create('t_user', function (Blueprint $table) {
            $table->integer('id_user', true);
            $table->string('username', 50);
            $table->string('password', 255);
            $table->enum('role', ['Admin', 'Siswa', 'Pustakawan', '']);
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_user') || DB::table('t_user')->exists()) {
            return;
        }

        Schema::dropIfExists('t_user');
    }
};

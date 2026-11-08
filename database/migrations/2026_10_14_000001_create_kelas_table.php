<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->collation = 'utf8mb4_unicode_ci';
            $table->integer('id_kelas', true);
            $table->string('nama_kelas', 50);
            $table->string('tingkat', 20)->nullable();
            $table->string('wali_kelas', 100)->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        // Inisialisasi daftar kelas dari data siswa yang sudah ada
        $daftar = DB::table('siswa')
            ->whereNotNull('kelas')
            ->where('kelas', '<>', '')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        foreach ($daftar as $nama) {
            $nama = trim((string) $nama);

            DB::table('kelas')->insert([
                'nama_kelas' => $nama,
                'tingkat'    => $nama === '' ? null : explode(' ', $nama)[0],
                'wali_kelas' => null,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};

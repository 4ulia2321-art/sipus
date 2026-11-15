<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->integer('id_kelas')->nullable()->after('kelas');
            $table->index('id_kelas', 'siswa_id_kelas_index');
        });

        // Isi id_kelas dari nama kelas yang sudah tersimpan pada kolom kelas.
        // Data lama tetap dipertahankan; siswa dengan kelas yang tidak dikenal
        // dibiarkan null agar migrasi tidak gagal.
        $petaKelas = DB::table('kelas')->pluck('id_kelas', 'nama_kelas');

        DB::table('siswa')
            ->select('id_siswa', 'kelas')
            ->orderBy('id_siswa')
            ->chunkById(500, function ($rows) use ($petaKelas) {
                foreach ($rows as $row) {
                    $idKelas = $petaKelas[trim((string) $row->kelas)] ?? null;

                    if ($idKelas !== null) {
                        DB::table('siswa')
                            ->where('id_siswa', $row->id_siswa)
                            ->update(['id_kelas' => $idKelas]);
                    }
                }
            }, 'id_siswa');

        Schema::table('siswa', function (Blueprint $table) {
            $table->foreign('id_kelas', 'siswa_id_kelas_foreign')
                ->references('id_kelas')
                ->on('kelas')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign('siswa_id_kelas_foreign');
            $table->dropIndex('siswa_id_kelas_index');
            $table->dropColumn('id_kelas');
        });
    }
};

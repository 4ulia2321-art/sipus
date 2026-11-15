<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jangan gagal bila tabel hasil impor sudah memiliki salah satu
        // atau kedua kolom reset password.
        if (! Schema::hasTable('t_user')) {
            return;
        }

        $kolomHilang = array_values(array_filter(
            ['reset_requested_at', 'reset_approved_at'],
            fn (string $kolom): bool => ! Schema::hasColumn('t_user', $kolom)
        ));

        if ($kolomHilang === []) {
            return;
        }

        Schema::table('t_user', function (Blueprint $table) use ($kolomHilang) {
            if (in_array('reset_requested_at', $kolomHilang, true)) {
                // Kapan pengguna mengajukan permintaan reset password.
                $table->dateTime('reset_requested_at')->nullable()->after('role');
            }

            if (in_array('reset_approved_at', $kolomHilang, true)) {
                // Kapan admin mengonfirmasi permintaan reset tersebut.
                $table->dateTime('reset_approved_at')->nullable()->after('reset_requested_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_user')) {
            return;
        }

        $kolomAda = array_values(array_filter(
            ['reset_requested_at', 'reset_approved_at'],
            fn (string $kolom): bool => Schema::hasColumn('t_user', $kolom)
        ));

        if ($kolomAda === []) {
            return;
        }

        Schema::table('t_user', function (Blueprint $table) use ($kolomAda) {
            $table->dropColumn($kolomAda);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel hasil impor production bisa saja sudah memiliki kolom name
        // walaupun migrasi ini belum tercatat di tabel migrations.
        if (! Schema::hasTable('t_user') || Schema::hasColumn('t_user', 'name')) {
            return;
        }

        Schema::table('t_user', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('t_user', 'name')) {
            return;
        }

        Schema::table('t_user', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};

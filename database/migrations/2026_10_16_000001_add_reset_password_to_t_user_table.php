<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_user', function (Blueprint $table) {
            // Kapan pengguna mengajukan permintaan reset password.
            $table->dateTime('reset_requested_at')->nullable()->after('role');
            // Kapan admin mengonfirmasi permintaan reset tersebut.
            $table->dateTime('reset_approved_at')->nullable()->after('reset_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('t_user', function (Blueprint $table) {
            $table->dropColumn(['reset_requested_at', 'reset_approved_at']);
        });
    }
};

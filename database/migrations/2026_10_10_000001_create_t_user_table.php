<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists('t_user');
    }
};
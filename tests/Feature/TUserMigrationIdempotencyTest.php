<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TUserMigrationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Migrasi yang berhubungan langsung dengan tabel t_user.
     *
     * @var list<string>
     */
    private const MIGRASI_T_USER = [
        '2026_10_10_000001_create_t_user_table',
        '2026_10_15_000001_add_name_to_t_user_table',
        '2026_10_16_000001_add_reset_password_to_t_user_table',
    ];

    /**
     * Hapus catatan migrasi saja, tanpa menyentuh tabel atau datanya.
     * Ini meniru database production yang tabelnya sudah diimpor lebih dulu.
     */
    private function lupakanCatatanMigrasiTUser(): void
    {
        DB::table('migrations')
            ->whereIn('migration', self::MIGRASI_T_USER)
            ->delete();
    }

    public function test_migrasi_tidak_membuat_ulang_t_user_dan_mempertahankan_akun(): void
    {
        $password = Hash::make('rahasia123');

        DB::table('t_user')->insert([
            'username' => 'akun-production@sipus.sch.id',
            'name' => 'Akun Production',
            'password' => $password,
            'role' => 'Admin',
        ]);

        $this->lupakanCatatanMigrasiTUser();

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('t_user'));
        $this->assertTrue(Schema::hasColumns('t_user', [
            'id_user',
            'username',
            'name',
            'password',
            'role',
            'reset_requested_at',
            'reset_approved_at',
            'created_at',
        ]));

        $akun = DB::table('t_user')
            ->where('username', 'akun-production@sipus.sch.id')
            ->first();

        $this->assertNotNull($akun);
        $this->assertSame('Akun Production', $akun->name);
        $this->assertSame($password, $akun->password);
        $this->assertSame('Admin', $akun->role);
        $this->assertSame(1, DB::table('t_user')->count());

        foreach (self::MIGRASI_T_USER as $migrasi) {
            $this->assertDatabaseHas('migrations', ['migration' => $migrasi]);
        }
    }

    public function test_migrasi_menambahkan_kolom_yang_hilang_tanpa_menghapus_akun(): void
    {
        // Tiru t_user lama hasil impor yang belum memiliki kolom tambahan.
        Schema::table('t_user', function (Blueprint $table) {
            $table->dropColumn(['name', 'reset_requested_at', 'reset_approved_at']);
        });

        $password = Hash::make('rahasia123');

        DB::table('t_user')->insert([
            'username' => 'akun-lama@sipus.sch.id',
            'password' => $password,
            'role' => 'Siswa',
        ]);

        $this->lupakanCatatanMigrasiTUser();

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasColumns('t_user', [
            'name',
            'reset_requested_at',
            'reset_approved_at',
        ]));

        $akun = DB::table('t_user')
            ->where('username', 'akun-lama@sipus.sch.id')
            ->first();

        $this->assertNotNull($akun);
        $this->assertNull($akun->name);
        $this->assertNull($akun->reset_requested_at);
        $this->assertNull($akun->reset_approved_at);
        $this->assertSame($password, $akun->password);
        $this->assertSame('Siswa', $akun->role);
        $this->assertSame(1, DB::table('t_user')->count());
    }

    public function test_rollback_tidak_menghapus_t_user_yang_berisi_data(): void
    {
        DB::table('t_user')->insert([
            'username' => 'akun-rollback@sipus.sch.id',
            'name' => 'Akun Rollback',
            'password' => Hash::make('rahasia123'),
            'role' => 'Pustakawan',
        ]);

        $migrasi = require database_path('migrations/2026_10_10_000001_create_t_user_table.php');
        $migrasi->down();

        $this->assertTrue(Schema::hasTable('t_user'));
        $this->assertDatabaseHas('t_user', [
            'username' => 'akun-rollback@sipus.sch.id',
            'role' => 'Pustakawan',
        ]);
    }
}

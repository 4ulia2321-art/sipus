<?php

namespace Tests\Feature;

use App\Models\TUser;
use Database\Seeders\DataAnggotaMassalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataAnggotaMassalSeederTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): TUser
    {
        $id = DB::table('t_user')->insertGetId([
            'username'   => $username,
            'name'       => ucfirst($username),
            'password'   => Hash::make('secret'),
            'role'       => $role,
            'created_at' => now(),
        ]);

        return TUser::findOrFail($id);
    }

    public function test_seeder_melengkapi_anggota_dan_kelas_secara_idempoten(): void
    {
        $this->seed(DataAnggotaMassalSeeder::class);

        $this->assertSame(1020, DB::table('siswa')->count());
        $this->assertSame(1020, DB::table('t_user')->where('role', 'Siswa')->count());
        $this->assertSame(30, DB::table('kelas')->count());
        $this->assertSame(30, DB::table('kelas')->distinct()->count('nama_kelas'));

        foreach (['VII', 'VIII', 'IX'] as $tingkat) {
            $this->assertSame(10, DB::table('kelas')->where('tingkat', $tingkat)->count());

            foreach (range('A', 'J') as $huruf) {
                $this->assertSame(
                    34,
                    DB::table('siswa')->where('kelas', "{$tingkat} {$huruf}")->count()
                );
            }
        }

        // Dijalankan ulang tidak boleh menggandakan data.
        $this->seed(DataAnggotaMassalSeeder::class);

        $this->assertSame(1020, DB::table('siswa')->count());
        $this->assertSame(1020, DB::table('t_user')->where('role', 'Siswa')->count());
        $this->assertSame(30, DB::table('kelas')->count());
    }

    public function test_dashboard_dan_laporan_menampilkan_total_anggota_yang_sama(): void
    {
        $this->seed(DataAnggotaMassalSeeder::class);

        $admin = $this->buatUser('Admin', 'adminanggota');
        $pustakawan = $this->buatUser('Pustakawan', 'pustakawananggota');

        $this->actingAs($pustakawan)
            ->get('/pustakawan')
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                $kartu = collect($stats)->keyBy('label');

                return $kartu['Total Anggota']['value'] === 1020;
            });

        $this->actingAs($pustakawan)
            ->get('/pustakawan/laporan')
            ->assertOk()
            ->assertViewHas('stat', fn (array $stat): bool => $stat['anggota'] === 1020);

        $this->actingAs($admin)
            ->get('/admin/laporan')
            ->assertOk()
            ->assertViewHas('stat', fn (array $stat): bool => $stat['anggota'] === 1020);

        $this->actingAs($admin)
            ->get('/admin/statistik')
            ->assertOk()
            ->assertViewHas('stat', fn (array $stat): bool => $stat['anggota'] === 1020);

        $this->actingAs($admin)
            ->get('/admin/kelas')
            ->assertOk()
            ->assertViewHas('stat', function (array $stat): bool {
                return $stat['total'] === 30
                    && $stat['tingkat7'] === 10
                    && $stat['tingkat8'] === 10
                    && $stat['tingkat9'] === 10;
            });
    }
}

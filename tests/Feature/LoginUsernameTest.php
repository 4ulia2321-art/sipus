<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoginUsernameTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): User
    {
        $id = DB::table('t_user')->insertGetId([
            'username' => $username,
            'name'     => ucfirst($username),
            'password' => Hash::make('rahasia123'),
            'role'     => $role,
        ]);

        return User::findOrFail($id);
    }

    public function test_model_user_memakai_struktur_t_user(): void
    {
        $user = new User();

        $this->assertSame('t_user', $user->getTable());
        $this->assertSame('id_user', $user->getKeyName());
        $this->assertFalse($user->usesTimestamps());
        $this->assertSame(
            ['username', 'name', 'password', 'role'],
            $user->getFillable()
        );
    }

    public function test_admin_pustakawan_dan_siswa_bisa_login_dengan_username(): void
    {
        $kasus = [
            ['Admin', 'adminlogin', '/admin'],
            ['Pustakawan', 'pustakawanlogin', '/pustakawan'],
            ['Siswa', 'siswalogin', null],
        ];

        foreach ($kasus as [$role, $username, $tujuan]) {
            $user = $this->buatUser($role, $username);
            $hashSebelum = DB::table('t_user')->where('id_user', $user->id_user)->value('password');

            $this->post('/login', [
                'username' => $username,
                'password' => 'rahasia123',
            ])->assertRedirect('/dashboard');

            $this->assertAuthenticatedAs($user);

            // Password bcrypt yang sudah ada tidak boleh berubah saat login.
            $this->assertSame(
                $hashSebelum,
                DB::table('t_user')->where('id_user', $user->id_user)->value('password')
            );

            $dashboard = $this->get('/dashboard');

            if ($tujuan === null) {
                $dashboard->assertOk();
            } else {
                $dashboard->assertRedirect($tujuan);
            }

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_login_tetap_berjalan_tanpa_kolom_remember_token(): void
    {
        $user = $this->buatUser('Admin', 'adminremember');

        $this->post('/login', [
            'username' => 'adminremember',
            'password' => 'rahasia123',
            'remember' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_toleran_terhadap_spasi_dan_huruf_besar_kecil(): void
    {
        $user = $this->buatUser('Admin', 'AdminSpasi');

        // Simulasikan data production lama yang menyimpan spasi ekstra.
        DB::table('t_user')->where('id_user', $user->id_user)->update([
            'username' => '  AdminSpasi  ',
        ]);

        $this->assertSame(
            $user->id_user,
            User::findByUsername('adminspasi')?->id_user
        );

        $this->post('/login', [
            'username' => '  adminspasi  ',
            'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_log_kegagalan_login_tidak_memuat_password_atau_hash(): void
    {
        $this->buatUser('Admin', 'adminlog');
        Log::spy();

        $this->post('/login', [
            'username' => 'adminlog',
            'password' => 'password-salah',
        ])->assertSessionHas('error');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $pesan, array $konteks): bool {
                return $pesan === 'Login SIPUS gagal'
                    && $konteks['username'] === 'adminlog'
                    && $konteks['user_ditemukan'] === true
                    && $konteks['password_valid'] === false
                    && $konteks['alasan'] === 'password_tidak_cocok'
                    && ! array_key_exists('password', $konteks)
                    && ! str_contains((string) json_encode($konteks), '$2y$');
            })
            ->once();

        $this->assertGuest();
    }

    public function test_login_menolak_nis_dan_email_karena_harus_username(): void
    {
        $user = $this->buatUser('Siswa', '2024999@siswa.sipus.sch.id');

        DB::table('siswa')->insert([
            'id_user'    => $user->id_user,
            'nis'        => '2024999',
            'nama_siswa' => 'Siswa Uji Login',
            'kelas'      => 'VII A',
        ]);

        // NIS saja bukan username, jadi harus ditolak.
        $this->post('/login', [
            'username' => '2024999',
            'password' => 'rahasia123',
        ])->assertSessionHas('error');

        $this->assertGuest();

        // Email dari tabel users juga bukan identitas login SIPUS.
        DB::table('users')->insert([
            'name'       => 'Email Uji',
            'email'      => 'emailuji@example.com',
            'password'   => Hash::make('rahasia123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/login', [
            'username' => 'emailuji@example.com',
            'password' => 'rahasia123',
        ])->assertSessionHas('error');

        $this->assertGuest();

        // Username lengkap tetap bisa dipakai.
        $this->post('/login', [
            'username' => '2024999@siswa.sipus.sch.id',
            'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_menolak_password_salah_dan_role_tidak_dikenal(): void
    {
        $this->buatUser('Admin', 'adminsalah');

        $this->post('/login', [
            'username' => 'adminsalah',
            'password' => 'password-salah',
        ])->assertSessionHas('error');

        $this->assertGuest();

        $tanpaRole = $this->buatUser('', 'tanparole');

        $this->post('/login', [
            'username' => 'tanparole',
            'password' => 'rahasia123',
        ])->assertSessionHas('error');

        $this->assertGuest();
        $this->assertNotSame($tanpaRole->id_user, auth()->id());
    }
}

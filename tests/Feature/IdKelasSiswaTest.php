<?php

namespace Tests\Feature;

use App\Models\TUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdKelasSiswaTest extends TestCase
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

    private function buatKelas(string $nama): int
    {
        return DB::table('kelas')->insertGetId([
            'nama_kelas' => $nama,
            'tingkat'    => explode(' ', $nama)[0],
            'created_at' => now(),
        ]);
    }

    public function test_tambah_dan_ubah_anggota_mengisi_id_kelas_sesuai_nama_kelas(): void
    {
        $idKelasAwal = $this->buatKelas('VII C');
        $idKelasBaru = $this->buatKelas('VIII D');

        $admin = $this->buatUser('Admin', 'adminidkelas');

        $this->actingAs($admin)
            ->post('/admin/anggota', [
                'username'   => 'siswaidkelas@example.test',
                'password'   => '12345678',
                'nis'        => '991001',
                'nama_siswa' => 'Siswa Id Kelas',
                'kelas'      => 'VII C',
            ])
            ->assertRedirect('/admin/anggota');

        $siswa = DB::table('siswa')->where('nis', '991001')->first();
        $this->assertNotNull($siswa);
        $this->assertSame($idKelasAwal, $siswa->id_kelas);

        $this->actingAs($admin)
            ->put('/admin/anggota/' . $siswa->id_siswa, [
                'username'   => 'siswaidkelas@example.test',
                'nis'        => '991001',
                'nama_siswa' => 'Siswa Id Kelas',
                'kelas'      => 'VIII D',
            ])
            ->assertRedirect('/admin/anggota');

        $this->assertSame(
            $idKelasBaru,
            DB::table('siswa')->where('id_siswa', $siswa->id_siswa)->value('id_kelas')
        );
    }

    public function test_siswa_mengubah_kelas_di_profil_ikut_memperbarui_id_kelas(): void
    {
        $idKelas = $this->buatKelas('IX A');
        $user = $this->buatUser('Siswa', 'siswaidkelasprofil');

        $idSiswa = DB::table('siswa')->insertGetId([
            'id_user'    => $user->id_user,
            'nis'        => '991002',
            'nama_siswa' => 'Siswa Profil',
            'kelas'      => 'VII A',
        ]);

        $this->actingAs($user)
            ->put('/profil', [
                'username'   => 'siswaidkelasprofil',
                'nis'        => '991002',
                'nama_siswa' => 'Siswa Profil',
                'kelas'      => 'IX A',
            ])
            ->assertRedirect('/profil');

        $this->assertSame(
            $idKelas,
            DB::table('siswa')->where('id_siswa', $idSiswa)->value('id_kelas')
        );
    }
}

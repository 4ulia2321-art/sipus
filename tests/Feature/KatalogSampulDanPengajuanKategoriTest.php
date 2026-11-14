<?php

namespace Tests\Feature;

use App\Models\TUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KatalogSampulDanPengajuanKategoriTest extends TestCase
{
    use RefreshDatabase;

    private function buatKategori(string $nama): int
    {
        return DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => $nama,
            'keterangan'    => 'Kategori uji',
        ]);
    }

    private function buatBuku(
        int $idKategori,
        string $judul,
        string $kategori,
        string $cover,
        int $stok = 5,
    ): int {
        return DB::table('buku')->insertGetId([
            'id_kategori'  => $idKategori,
            'no_seri_buku' => 'UJI-' . uniqid(),
            'judul'        => $judul,
            'pengarang'    => 'Penulis Uji',
            'penerbit'     => 'Penerbit Uji',
            'tahun_terbit' => 2026,
            'kategori'     => $kategori,
            'lokasi_rak'   => 'R1',
            'stok'         => $stok,
            'cover'        => $cover,
            'created_at'   => now(),
        ]);
    }

    private function buatSiswa(string $username, string $nis): TUser
    {
        $idUser = DB::table('t_user')->insertGetId([
            'username'   => $username,
            'name'       => 'Siswa Uji',
            'password'   => Hash::make('secret'),
            'role'       => 'Siswa',
            'created_at' => now(),
        ]);

        DB::table('siswa')->insert([
            'id_user'    => $idUser,
            'nis'        => $nis,
            'nama_siswa' => 'Siswa ' . $nis,
            'kelas'      => 'VII A',
        ]);

        return TUser::findOrFail($idUser);
    }

    public function test_perintah_perbaiki_sampul_menyesuaikan_judul_dan_kategori(): void
    {
        Storage::fake('public');

        $idPelajaran = $this->buatKategori('Pelajaran');
        $idCerita = $this->buatKategori('Cerita');

        $this->buatBuku(
            $idPelajaran,
            'Pendidikan Agama Islam dan Budi Pekerti Kelas VIII',
            'Pelajaran',
            'covers/SMP-035.svg'
        );
        $this->buatBuku(
            $idCerita,
            'Malin Kundang dan Cerita Rakyat Lainnya',
            'Cerita',
            'covers/SMP-029.svg'
        );

        $this->artisan('sipus:perbaiki-sampul')->assertSuccessful();

        Storage::disk('public')->assertExists('covers/SMP-035.svg');
        Storage::disk('public')->assertExists('covers/SMP-029.svg');

        $sampulPelajaran = Storage::disk('public')->get('covers/SMP-035.svg');
        $this->assertStringContainsString('KURIKULUM MERDEKA', $sampulPelajaran);
        $this->assertStringContainsString('PAI', $sampulPelajaran);
        $this->assertStringContainsString('KELAS VIII', $sampulPelajaran);
        $this->assertStringContainsString('Pendidikan Agama', $sampulPelajaran);
        $this->assertStringNotContainsString('BUKU CERITA', $sampulPelajaran);
        $this->assertStringNotContainsString('Malin Kundang', $sampulPelajaran);

        $sampulCerita = Storage::disk('public')->get('covers/SMP-029.svg');
        $this->assertStringContainsString('BUKU CERITA', $sampulCerita);
        $this->assertStringContainsString('Malin Kundang', $sampulCerita);
        $this->assertStringContainsString('Penulis Uji', $sampulCerita);
        $this->assertStringNotContainsString('KURIKULUM MERDEKA', $sampulCerita);
        $this->assertStringNotContainsString('Pendidikan Agama', $sampulCerita);
    }

    public function test_pengajuan_siswa_memisahkan_buku_per_kategori(): void
    {
        $siswa = $this->buatSiswa('siswa-kategori', '9001');

        $idPelajaran = $this->buatKategori('Pelajaran');
        $idCerita = $this->buatKategori('Cerita');
        $idPelajaranLain = $this->buatKategori('Pelajaran Lain');

        $this->buatBuku($idPelajaran, 'Buku Pelajaran Uji', 'Pelajaran', 'covers/UJI-PELAJARAN.svg');
        $this->buatBuku($idCerita, 'Buku Cerita Uji', 'Cerita', 'covers/UJI-CERITA.svg');
        $this->buatBuku($idPelajaranLain, 'Buku Pendamping Uji', 'Pelajaran Lain', 'covers/UJI-LAIN.svg');
        $this->buatBuku($idPelajaran, 'Buku Pelajaran Habis', 'Pelajaran', 'covers/UJI-HABIS.svg', 0);

        $this->actingAs($siswa)
            ->get('/pengajuan')
            ->assertOk()
            ->assertSee('Buku Pelajaran')
            ->assertSee('Buku Cerita')
            ->assertDontSee('Buku Pelajaran Lain')
            ->assertSeeInOrder([
                'Buku Pelajaran',
                'Buku Pelajaran Uji',
                'Buku Cerita',
                'Buku Cerita Uji',
            ])
            ->assertDontSee('Buku Pendamping Uji')
            ->assertDontSee('Buku Pelajaran Habis');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AntiDuplikatBukuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function kategori(): int
    {
        return DB::table('kategori_buku')->insertGetId([
            'nama_kategori' => 'Pelajaran',
            'keterangan'    => 'Kategori untuk pengujian',
        ]);
    }

    /**
     * @param array<string, mixed> $ganti
     * @return array<string, mixed>
     */
    private function dataBuku(int $idKategori, array $ganti = []): array
    {
        return array_merge([
            'no_seri_buku' => 'BKU-001',
            'judul'        => 'Matematika Kelas VII',
            'pengarang'    => 'Penulis Uji',
            'penerbit'     => 'Penerbit Uji',
            'id_kategori'  => $idKategori,
            'tahun_terbit' => 2024,
            'lokasi_rak'   => 'Rak 1',
            'stok'         => 2,
        ], $ganti);
    }

    public function test_admin_menolak_judul_dan_kode_buku_ganda(): void
    {
        $this->actingAs($this->admin());

        $kategori = $this->kategori();

        $this->post('/admin/buku', $this->dataBuku($kategori))
            ->assertRedirect('/admin/buku');

        $this->assertDatabaseHas('buku', [
            'judul'        => 'Matematika Kelas VII',
            'no_seri_buku' => 'BKU-001',
        ]);

        // Judul sama dengan spasi dan kapitalisasi berbeda harus ditolak.
        $this->post('/admin/buku', $this->dataBuku($kategori, [
            'no_seri_buku' => 'BKU-002',
            'judul'        => '  matematika kelas vii  ',
        ]))->assertSessionHasErrors('judul');

        // Kode sama dengan spasi dan kapitalisasi berbeda harus ditolak.
        $this->post('/admin/buku', $this->dataBuku($kategori, [
            'no_seri_buku' => '  bku-001  ',
            'judul'        => 'Buku Lain',
        ]))->assertSessionHasErrors('no_seri_buku');

        $this->assertSame(1, DB::table('buku')->count());
    }

    public function test_admin_tidak_bisa_mengubah_buku_menjadi_duplikat(): void
    {
        $this->actingAs($this->admin());

        $kategori = $this->kategori();

        $this->post('/admin/buku', $this->dataBuku($kategori));
        $this->post('/admin/buku', $this->dataBuku($kategori, [
            'no_seri_buku' => 'BKU-002',
            'judul'        => 'Bahasa Indonesia Kelas VII',
        ]));

        $idPertama = (int) DB::table('buku')->where('judul', 'Matematika Kelas VII')->value('id_buku');
        $idKedua = (int) DB::table('buku')->where('judul', 'Bahasa Indonesia Kelas VII')->value('id_buku');

        // Menyimpan nilai milik sendiri harus tetap boleh.
        $this->put("/admin/buku/{$idKedua}", $this->dataBuku($kategori, [
            'no_seri_buku' => 'BKU-002',
            'judul'        => 'Bahasa Indonesia Kelas VII',
        ]))->assertRedirect('/admin/buku')
            ->assertSessionHasNoErrors();

        // Tidak boleh memakai judul buku lain.
        $this->put("/admin/buku/{$idKedua}", $this->dataBuku($kategori, [
            'no_seri_buku' => 'BKU-003',
            'judul'        => 'matematika kelas vii',
        ]))->assertSessionHasErrors('judul');

        // Tidak boleh memakai kode buku lain.
        $this->put("/admin/buku/{$idKedua}", $this->dataBuku($kategori, [
            'no_seri_buku' => 'bku-001',
            'judul'        => 'Bahasa Indonesia Kelas VII',
        ]))->assertSessionHasErrors('no_seri_buku');

        $this->assertDatabaseHas('buku', [
            'id_buku'      => $idKedua,
            'no_seri_buku' => 'BKU-002',
            'judul'        => 'Bahasa Indonesia Kelas VII',
        ]);
        $this->assertNotSame($idPertama, $idKedua);
    }

    public function test_admin_menolak_nama_kelas_ganda(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/kelas', [
            'nama_kelas' => 'VII A',
            'tingkat'    => 'VII',
        ])->assertRedirect('/admin/kelas');

        $this->post('/admin/kelas', [
            'nama_kelas' => '  vii a  ',
            'tingkat'    => 'VII',
        ])->assertSessionHasErrors('nama_kelas');

        $this->assertSame(1, DB::table('kelas')->count());

        $id = (int) DB::table('kelas')->value('id_kelas');

        // Menyimpan nama kelas yang sama pada baris yang sama harus boleh.
        $this->put("/admin/kelas/{$id}", [
            'nama_kelas' => 'VII A',
            'tingkat'    => 'VII',
        ])->assertRedirect('/admin/kelas')
            ->assertSessionHasNoErrors();

        DB::table('kelas')->insert([
            'nama_kelas' => 'VIII B',
            'tingkat'    => 'VIII',
            'created_at' => now(),
        ]);

        $this->put("/admin/kelas/{$id}", [
            'nama_kelas' => 'viii b',
            'tingkat'    => 'VII',
        ])->assertSessionHasErrors('nama_kelas');
    }
}

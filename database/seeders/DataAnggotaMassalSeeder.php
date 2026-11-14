<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Melengkapi data anggota menjadi 1.020 siswa (34 siswa per kelas)
 * pada 30 kelas: VII, VIII, dan IX dari A sampai J.
 *
 * Seeder ini idempoten: data yang sudah ada tidak dihapus dan seeder
 * aman dijalankan ulang tanpa membuat anggota atau kelas ganda.
 */
class DataAnggotaMassalSeeder extends Seeder
{
    private const TINGKAT = ['VII', 'VIII', 'IX'];

    private const HURUF = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    private const PER_KELAS = 34;

    public function run(): void
    {
        $this->sinkronKelas();

        $jumlahKelas = count(self::TINGKAT) * count(self::HURUF);
        $target = $jumlahKelas * self::PER_KELAS;

        if (DB::table('siswa')->count() >= $target) {
            return;
        }

        $namaDepanL = [
            'Ahmad', 'Aditya', 'Agus', 'Andi', 'Asep', 'Bagas', 'Bambang', 'Bayu',
            'Budi', 'Dedi', 'Deni', 'Dimas', 'Eko', 'Fajar', 'Galih', 'Hendra',
            'Iwan', 'Joko', 'Kevin', 'Lutfi', 'Muhammad', 'Nanda', 'Rafi', 'Rangga',
            'Rizky', 'Sandi', 'Setia', 'Taufik', 'Wahyu', 'Yoga',
        ];

        $namaDepanP = [
            'Aisyah', 'Anisa', 'Bunga', 'Citra', 'Dewi', 'Eka', 'Fitri', 'Gita',
            'Indah', 'Intan', 'Kartika', 'Lestari', 'Maya', 'Nabila', 'Nurul',
            'Putri', 'Rina', 'Sari', 'Siti', 'Wulan', 'Yuni', 'Zahra', 'Ayu',
            'Dinda', 'Farah', 'Hana', 'Ika', 'Lia', 'Mega', 'Ratna',
        ];

        $namaBelakang = [
            'Pratama', 'Saputra', 'Nugroho', 'Wijaya', 'Lestari', 'Permata',
            'Wulandari', 'Santoso', 'Setiawan', 'Hidayat', 'Kurniawan', 'Sari',
            'Dewi', 'Putri', 'Ramadhan', 'Maulana', 'Firmansyah', 'Hakim',
            'Siregar', 'Simanjuntak', 'Sitorus', 'Pasaribu', 'Nainggolan',
            'Situmorang', 'Hutapea', 'Suryana', 'Gunawan', 'Rahayu', 'Anggraini',
            'Safitri', 'Oktaviani', 'Puspita', 'Handayani', 'Kusuma', 'Utami',
            'Purnama', 'Fadillah', 'Arifin', 'Mulyani', 'Rahmawati',
        ];

        $kota = [
            'Bandung', 'Cimahi', 'Garut', 'Sumedang', 'Subang', 'Cianjur',
            'Bandung Barat', 'Purwakarta', 'Tasikmalaya', 'Majalengka',
        ];

        $nisTerpakai = DB::table('siswa')->pluck('nis')->flip();
        $nisUrut = 1000;
        $namaUrut = 0;

        // Hash dibuat sekali karena semua akun baru memakai password yang sama.
        $password = Hash::make('12345678');

        DB::transaction(function () use (
            $namaDepanL,
            $namaDepanP,
            $namaBelakang,
            $kota,
            $password,
            &$nisTerpakai,
            &$nisUrut,
            &$namaUrut
        ) {
            foreach (self::TINGKAT as $tingkat) {
                foreach (self::HURUF as $huruf) {
                    $kelas = "{$tingkat} {$huruf}";
                    $sudahAda = DB::table('siswa')->where('kelas', $kelas)->count();
                    $butuh = max(0, self::PER_KELAS - $sudahAda);

                    for ($i = 0; $i < $butuh; $i++) {
                        do {
                            $nis = '2024' . str_pad((string) $nisUrut++, 4, '0', STR_PAD_LEFT);
                        } while ($nisTerpakai->has($nis));

                        $nisTerpakai[$nis] = true;

                        $perempuan = $namaUrut % 2 === 1;
                        $depan = $perempuan ? $namaDepanP : $namaDepanL;
                        $nama = $depan[$namaUrut % count($depan)] . ' '
                            . $namaBelakang[intdiv($namaUrut, count($depan)) % count($namaBelakang)];

                        $tahun = match ($tingkat) {
                            'VII' => 2011,
                            'VIII' => 2010,
                            default => 2009,
                        };
                        $bulan = ($namaUrut % 12) + 1;
                        $hari = ($namaUrut % 28) + 1;

                        $idUser = DB::table('t_user')->insertGetId([
                            'username' => $nis . '@siswa.sipus.sch.id',
                            'password' => $password,
                            'role'     => 'Siswa',
                        ]);

                        DB::table('siswa')->insert([
                            'id_user'       => $idUser,
                            'nis'           => $nis,
                            'nama_siswa'    => $nama,
                            'kelas'         => $kelas,
                            'jenis_kelamin' => $perempuan ? 'Perempuan' : 'Laki-laki',
                            'tempat_lahir'  => $kota[$namaUrut % count($kota)],
                            'tanggal_lahir' => Carbon::create($tahun, $bulan, $hari)->toDateString(),
                            'alamat'        => 'Jl. Pendidikan No. ' . ($namaUrut + 1) . ', Bandung',
                            'no_telp'       => '0812' . str_pad((string) (10000000 + $namaUrut), 8, '0', STR_PAD_LEFT),
                        ]);

                        $namaUrut++;
                    }
                }
            }
        });
    }

    /**
     * Rapikan daftar kelas: hapus duplikat nama dan pastikan 30 kelas
     * VII–IX A–J tersedia beserta wali kelasnya.
     */
    private function sinkronKelas(): void
    {
        $duplikat = DB::table('kelas')
            ->select('nama_kelas')
            ->groupBy('nama_kelas')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('nama_kelas');

        foreach ($duplikat as $nama) {
            $id = DB::table('kelas')
                ->where('nama_kelas', $nama)
                ->orderBy('id_kelas')
                ->pluck('id_kelas');

            DB::table('kelas')->whereIn('id_kelas', $id->slice(1)->all())->delete();
        }

        $wali = [
            'VII A'  => 'Siti Nurhaliza, S.Pd.',
            'VII B'  => 'Ahmad Fauzi, S.Pd.',
            'VII C'  => 'Rina Marlina, S.Pd.',
            'VII D'  => 'Dedi Kurniawan, S.Pd.',
            'VII E'  => 'Nia Kurniasih, M.Pd.',
            'VII F'  => 'Agus Setiawan, S.Pd.',
            'VII G'  => 'Lilis Suryani, S.Pd.',
            'VII H'  => 'Hendra Gunawan, S.Pd.',
            'VII I'  => 'Maya Puspita, S.Pd.',
            'VII J'  => 'Yusuf Maulana, S.Pd.',
            'VIII A' => 'Dewi Kartika, M.Pd.',
            'VIII B' => 'Budi Santoso, S.Pd.',
            'VIII C' => 'Ani Rohaeni, S.Pd.',
            'VIII D' => 'Rudi Hartono, S.Pd.',
            'VIII E' => 'Wulan Sari, M.Pd.',
            'VIII F' => 'Deden Supriatna, S.Pd.',
            'VIII G' => 'Euis Komariah, S.Pd.',
            'VIII H' => 'Asep Saepudin, S.Pd.',
            'VIII I' => 'Fitri Handayani, S.Pd.',
            'VIII J' => 'Iwan Setiawan, S.Pd.',
            'IX A'   => 'Rina Marlina, S.Pd.',
            'IX B'   => 'Dadang Hermawan, S.Pd.',
            'IX C'   => 'Sri Wahyuni, M.Pd.',
            'IX D'   => 'Bambang Sutrisno, S.Pd.',
            'IX E'   => 'Nurul Hidayah, S.Pd.',
            'IX F'   => 'Endang Kusnadi, S.Pd.',
            'IX G'   => 'Tuti Herawati, S.Pd.',
            'IX H'   => 'Joko Susilo, S.Pd.',
            'IX I'   => 'Lestari Ningsih, S.Pd.',
            'IX J'   => 'Arief Rachman, S.Pd.',
        ];

        foreach ($wali as $nama => $waliKelas) {
            $tingkat = explode(' ', $nama)[0];
            $data = ['tingkat' => $tingkat, 'wali_kelas' => $waliKelas];

            if (DB::table('kelas')->where('nama_kelas', $nama)->exists()) {
                DB::table('kelas')->where('nama_kelas', $nama)->update($data);
            } else {
                DB::table('kelas')->insert($data + [
                    'nama_kelas' => $nama,
                    'created_at' => now(),
                ]);
            }
        }
    }
}

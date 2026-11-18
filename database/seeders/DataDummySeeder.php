<?php

namespace Database\Seeders;

use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Menambahkan 30 siswa, 30 buku, 6 kategori baru, dan 30 peminjaman
 * dummy beserta detail serta pengembaliannya.
 *
 * Seeder ini tidak menghapus data lama dan aman dijalankan berulang:
 * siswa dikenali dari NIS 20255xxx, buku dari kode DUM-xxx, dan
 * peminjaman hanya dibuat jika siswa dummy belum memiliki transaksi.
 */
class DataDummySeeder extends Seeder
{
    private const NIS_AWAL = 20255001;

    private const KODE_BUKU = 'DUM-';

    private const PASSWORD = '12345678';

    /**
     * Kelas yang dipakai siswa dummy. Kelas yang belum ada akan dibuat.
     */
    private const KELAS = [
        'VII A', 'VII B', 'VII C', 'VII D', 'VII E',
        'VIII A', 'VIII B', 'VIII C', 'VIII D', 'VIII E',
        'IX A', 'IX B', 'IX C', 'IX D', 'IX E',
    ];

    public function run(): void
    {
        $today = Carbon::today();

        $kategori = $this->siapkanKategori();
        $siswaIds = $this->siapkanSiswa();
        $bukuIds = $this->siapkanBuku($kategori);

        $sudahAda = DB::table('peminjaman')
            ->whereIn('id_siswa', $siswaIds)
            ->exists();

        if ($sudahAda) {
            $this->command?->info(
                'Data dummy siswa dan buku sudah tersedia. '
                .'Peminjaman tidak dibuat ulang agar tidak duplikat.'
            );

            return;
        }

        DB::transaction(function () use ($today, $siswaIds, $bukuIds) {
            $this->buatPeminjaman($today, $siswaIds, $bukuIds);
        });

        $this->command?->info(
            'Data dummy ditambahkan: 30 siswa, 30 buku, '
            .count($kategori).' kategori, 30 peminjaman, '
            .'detail dan pengembalian mengikuti status transaksi.'
        );
    }

    /**
     * Pastikan seluruh kategori yang dipakai tersedia.
     *
     * @return array<string, int>
     */
    private function siapkanKategori(): array
    {
        $daftar = [
            ['Pelajaran', 'Buku paket pelajaran SMP'],
            ['Cerita', 'Buku cerita, novel, dan dongeng'],
            ['Pelajaran Lain', 'Buku pendamping pelajaran'],
            ['Sains', 'Buku sains dan pengetahuan alam'],
            ['Sejarah', 'Buku sejarah dan tokoh Indonesia'],
            ['Teknologi', 'Buku teknologi dan keterampilan digital'],
            ['Bahasa', 'Buku bahasa dan kamus'],
            ['Seni dan Budaya', 'Buku seni, musik, dan budaya daerah'],
            ['Agama', 'Buku keagamaan dan akhlak'],
        ];

        $ids = [];

        foreach ($daftar as [$nama, $keterangan]) {
            $id = DB::table('kategori_buku')
                ->where('nama_kategori', $nama)
                ->value('id_kategori');

            if ($id === null) {
                $id = DB::table('kategori_buku')->insertGetId([
                    'nama_kategori' => $nama,
                    'keterangan' => $keterangan,
                ]);
            }

            $ids[$nama] = (int) $id;
        }

        return $ids;
    }

    /**
     * Buat siswa dummy beserta akun login. Siswa yang sudah ada dilewati.
     *
     * @return array<int, int>
     */
    private function siapkanSiswa(): array
    {
        $ids = [];
        $password = Hash::make(self::PASSWORD);

        foreach ($this->daftarSiswa() as $i => $siswa) {
            $nis = (string) (self::NIS_AWAL + $i);
            $username = $nis.'@siswa.sipus.sch.id';

            $existing = DB::table('siswa')->where('nis', $nis)->first();

            if ($existing) {
                $ids[] = (int) $existing->id_siswa;

                continue;
            }

            $tingkat = explode(' ', $siswa['kelas'])[0];

            $idUser = DB::table('t_user')->where('username', $username)->value('id_user');

            if ($idUser === null) {
                $idUser = DB::table('t_user')->insertGetId([
                    'username' => $username,
                    'name' => $siswa['nama'],
                    'password' => $password,
                    'role' => 'Siswa',
                ]);
            }

            $ids[] = DB::table('siswa')->insertGetId([
                'id_user' => $idUser,
                'nis' => $nis,
                'nama_siswa' => $siswa['nama'],
                'kelas' => $siswa['kelas'],
                'id_kelas' => $this->pastikanKelas($siswa['kelas'], $tingkat),
                'jenis_kelamin' => $siswa['jenis_kelamin'],
                'tempat_lahir' => $siswa['tempat_lahir'],
                'tanggal_lahir' => $siswa['tanggal_lahir'],
                'alamat' => 'Jl. Merdeka No. '.($i + 1).', Bandung',
                'no_telp' => '0813'.str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT),
            ]);
        }

        return $ids;
    }

    /**
     * Buat buku dummy beserta sampul. Buku yang sudah ada dilewati.
     *
     * @param  array<string, int>  $kategori
     * @return array<int, int>
     */
    private function siapkanBuku(array $kategori): array
    {
        Storage::disk('public')->makeDirectory('covers');

        $ids = [];

        foreach ($this->daftarBuku() as $i => $buku) {
            $kode = self::KODE_BUKU.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            $existing = DB::table('buku')->where('no_seri_buku', $kode)->value('id_buku');

            if ($existing !== null) {
                $ids[] = (int) $existing;

                continue;
            }

            $id = DB::table('buku')->insertGetId([
                'id_kategori' => $kategori[$buku['kategori']],
                'no_seri_buku' => $kode,
                'judul' => $buku['judul'],
                'pengarang' => $buku['pengarang'],
                'penerbit' => $buku['penerbit'],
                'tahun_terbit' => $buku['tahun'],
                'kategori' => $buku['kategori'],
                'lokasi_rak' => $buku['rak'],
                'stok' => 10 + ($i % 4),
            ]);

            $file = 'covers/'.$kode.'.svg';
            Storage::disk('public')->put(
                $file,
                $this->svgCover($buku['judul'], $buku['pengarang'], $buku['kategori'], $buku['warna'], $buku['aksen'])
            );
            DB::table('buku')->where('id_buku', $id)->update(['cover' => $file]);

            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * Buat 30 peminjaman dengan status bervariasi, lalu detail dan
     * pengembalian yang sesuai. Stok buku aktif otomatis dikurangi.
     *
     * @param  array<int, int>  $siswaIds
     * @param  array<int, int>  $bukuIds
     */
    private function buatPeminjaman(Carbon $today, array $siswaIds, array $bukuIds): void
    {
        $lamaPinjam = max(1, Pengaturan::int('lama_pinjam_hari', 7));
        $dendaPerHari = max(0, Pengaturan::int('denda_per_hari', 500));

        $dipakaiAktif = [];

        foreach ($this->rencanaPeminjaman() as $i => $rencana) {
            $tanggalPinjam = $today->copy()->subDays($rencana['pinjam']);
            $jatuhTempo = $tanggalPinjam->copy()->addDays($lamaPinjam);
            $siswaId = $siswaIds[$i % count($siswaIds)];
            $bukuId = $bukuIds[$i % count($bukuIds)];

            $dataPeminjaman = [
                'id_siswa' => $siswaId,
                'tanggal_pinjam' => $tanggalPinjam->toDateString(),
                'tanggal_jatuh_tempo' => $jatuhTempo->toDateString(),
                'status' => $rencana['status'],
                'alasan_penolakan' => $rencana['alasan'] ?? null,
                'pengajuan_kembali_at' => null,
            ];

            if (! empty($rencana['pengajuan_kembali'])) {
                $dataPeminjaman['pengajuan_kembali_at'] = now()->subDay();
            }

            $idPeminjaman = DB::table('peminjaman')->insertGetId($dataPeminjaman);

            // Satu judul utama per transaksi, ditambah judul kedua pada
            // sebagian transaksi agar detail peminjaman lebih bervariasi.
            $detail = [
                ['id_buku' => $bukuId, 'jumlah' => $i % 4 === 0 ? 2 : 1],
            ];

            if ($i % 3 === 0) {
                $detail[] = [
                    'id_buku' => $bukuIds[($i + 11) % count($bukuIds)],
                    'jumlah' => 1,
                ];
            }

            foreach ($detail as $baris) {
                DB::table('detail_peminjaman')->insert([
                    'id_peminjaman' => $idPeminjaman,
                    'id_buku' => $baris['id_buku'],
                    'jumlah' => $baris['jumlah'],
                ]);

                if ($rencana['status'] === 'Dipinjam') {
                    $dipakaiAktif[$baris['id_buku']] = ($dipakaiAktif[$baris['id_buku']] ?? 0) + $baris['jumlah'];
                }
            }

            if (! isset($rencana['kembali'])) {
                continue;
            }

            $tanggalKembali = $tanggalPinjam->copy()->addDays($rencana['kembali']);
            $telat = $tanggalKembali->gt($jatuhTempo)
                ? (int) $jatuhTempo->diffInDays($tanggalKembali)
                : 0;

            DB::table('pengembalian')->insert([
                'id_peminjaman' => $idPeminjaman,
                'tanggal_kembali' => $tanggalKembali->toDateString(),
                'denda' => $telat * $dendaPerHari,
                'kategori' => $telat > 0 ? 'Terlambat' : 'Tepat Waktu',
                'status_bayar' => $rencana['status'] === 'Menunggu Pembayaran' ? 'Belum Lunas' : 'Lunas',
            ]);
        }

        // Buku yang sedang dipinjam tidak lagi dihitung sebagai stok tersedia.
        foreach ($dipakaiAktif as $idBuku => $jumlah) {
            DB::table('buku')->where('id_buku', $idBuku)->decrement('stok', $jumlah);
        }
    }

    /**
     * Rencana 30 transaksi: 8 dikembalikan, 5 menunggu pembayaran,
     * 4 ditolak, 8 sedang dipinjam, dan 5 menunggu persetujuan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rencanaPeminjaman(): array
    {
        $rencana = [];

        // Dikembalikan: tanggal pinjam tersebar lintas bulan dan tahun.
        foreach ([
            [380, 2], [330, 3], [280, 5], [230, 7],
            [180, 2], [130, 6], [80, 3], [40, 5],
        ] as [$pinjam, $kembali]) {
            $rencana[] = [
                'status' => 'Dikembalikan',
                'pinjam' => $pinjam,
                'kembali' => $kembali,
            ];
        }

        // Sudah dikembalikan tetapi denda belum dibayar.
        foreach ([
            [150, 12], [120, 14], [95, 10], [60, 16], [25, 11],
        ] as [$pinjam, $kembali]) {
            $rencana[] = [
                'status' => 'Menunggu Pembayaran',
                'pinjam' => $pinjam,
                'kembali' => $kembali,
            ];
        }

        // Ditolak: alasan penolakan ikut disimpan.
        foreach ([
            [14, 'Buku sedang dalam proses perbaikan.'],
            [10, 'Stok buku tidak mencukupi untuk jumlah yang diajukan.'],
            [7, 'Siswa memiliki pinjaman aktif yang belum dikembalikan.'],
            [3, 'Data pengajuan tidak lengkap.'],
        ] as [$pinjam, $alasan]) {
            $rencana[] = [
                'status' => 'Ditolak',
                'pinjam' => $pinjam,
                'alasan' => $alasan,
            ];
        }

        // Sedang dipinjam: sebagian sudah terlambat, dua sudah diajukan kembali.
        foreach ([
            [45, false], [30, false], [21, false], [14, false],
            [10, false], [6, true], [3, true], [1, false],
        ] as [$pinjam, $pengajuanKembali]) {
            $rencana[] = [
                'status' => 'Dipinjam',
                'pinjam' => $pinjam,
                'pengajuan_kembali' => $pengajuanKembali,
            ];
        }

        // Menunggu persetujuan pustakawan.
        foreach ([2, 1, 0, 0, 0] as $pinjam) {
            $rencana[] = [
                'status' => 'Menunggu',
                'pinjam' => $pinjam,
            ];
        }

        return $rencana;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function daftarSiswa(): array
    {
        $nama = [
            'Alya Rahmawati', 'Bima Prakoso', 'Chelsea Anindita',
            'Daffa Alfarizi', 'Elvira Maharani', 'Farhan Maulana',
            'Giselle Putri', 'Hafiz Abdurrahman', 'Intan Permatasari',
            'Jovan Aditya', 'Kirana Wulandari', 'Luthfi Hakim',
            'Melody Anggraini', 'Naufal Rizky', 'Olivia Safitri',
            'Pandu Wicaksono', 'Qanita Zahra', 'Raka Firmansyah',
            'Salma Nabila', 'Tegar Prasetyo', 'Ulfa Ramadhani',
            'Vino Saputra', 'Wanda Lestari', 'Xaverius Tanaya',
            'Yasmine Aulia', 'Zaki Mubarak', 'Anindya Kusuma',
            'Bagas Setiawan', 'Cinta Maharani', 'Dimas Ardiansyah',
        ];

        $tempat = ['Bandung', 'Cimahi', 'Garut', 'Sumedang', 'Subang', 'Cianjur'];

        $hasil = [];

        foreach ($nama as $i => $n) {
            $kelas = self::KELAS[$i % count(self::KELAS)];
            $tingkat = explode(' ', $kelas)[0];
            $tahun = match ($tingkat) {
                'VII' => 2013,
                'VIII' => 2012,
                default => 2011,
            };
            $perempuan = $i % 2 === 1;

            $hasil[] = [
                'nama' => $n,
                'kelas' => $kelas,
                'jenis_kelamin' => $perempuan ? 'Perempuan' : 'Laki-laki',
                'tempat_lahir' => $tempat[$i % count($tempat)],
                'tanggal_lahir' => Carbon::create($tahun, ($i % 12) + 1, ($i % 28) + 1)->toDateString(),
            ];
        }

        return $hasil;
    }

    /**
     * 30 buku dummy yang tersebar pada kategori Pelajaran, Cerita, dan
     * kategori baru agar halaman admin/pustakawan menampilkan variasi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function daftarBuku(): array
    {
        return [
            // Pelajaran
            $this->buku('Pelajaran', 'Matematika Terapan Kelas VII', 'Tim MGMP Matematika', 'Penerbit Sekolah', 2023, 'R-01', '#1d4ed8', '#93c5fd'),
            $this->buku('Pelajaran', 'Bahasa Indonesia Praktis Kelas VIII', 'Dra. Sri Wahyuni', 'Erlangga', 2022, 'R-01', '#c2410c', '#fdba74'),
            $this->buku('Pelajaran', 'Ilmu Pengetahuan Alam Terpadu Kelas IX', 'Tim Sains Nusantara', 'Yudhistira', 2021, 'R-01', '#15803d', '#86efac'),
            $this->buku('Pelajaran', 'Pendidikan Pancasila dan Kewarganegaraan Kelas VII', 'Drs. Ahmad Fauzi', 'Kemendikbudristek', 2022, 'R-01', '#b91c1c', '#fca5a5'),
            $this->buku('Pelajaran', 'Bahasa Inggris Communication Kelas VIII', 'English Club Team', 'Grafindo', 2023, 'R-02', '#6d28d9', '#c4b5fd'),
            $this->buku('Pelajaran', 'Informatika Dasar Kelas IX', 'Rizky Maulana, S.Kom.', 'Elex Media', 2024, 'R-03', '#0e7490', '#67e8f9'),
            $this->buku('Pelajaran', 'Seni Budaya dan Prakarya Kelas VII', 'Wulan Sari, S.Pd.', 'Balai Pustaka', 2020, 'R-04', '#b45309', '#fcd34d'),
            $this->buku('Pelajaran', 'Pendidikan Jasmani Kesehatan dan Olahraga Kelas VIII', 'Agus Setiawan, S.Pd.', 'Erlangga', 2022, 'R-04', '#0f766e', '#5eead4'),

            // Cerita
            $this->buku('Cerita', 'Petualangan di Pulau Harapan', 'Rina Kusuma', 'Bentang Pustaka', 2019, 'R-05', '#f97316', '#fbbf24'),
            $this->buku('Cerita', 'Misteri Perpustakaan Tua', 'Adi Nugraha', 'Gramedia', 2020, 'R-05', '#0ea5e9', '#22d3ee'),
            $this->buku('Cerita', 'Langit Senja di Cibaduyut', 'Maya Lestari', 'Republika', 2018, 'R-05', '#ef4444', '#fca5a5'),
            $this->buku('Cerita', 'Sahabat dari Negeri Seberang', 'Dewi Anggraini', 'Atria', 2021, 'R-06', '#6366f1', '#a5b4fc'),
            $this->buku('Cerita', 'Kancil dan Hutan Ajaib', 'Tim Dongeng Nusantara', 'Balai Pustaka', 2017, 'R-06', '#10b981', '#6ee7b7'),
            $this->buku('Cerita', 'Sepatu Bola Impian', 'Fajar Ramadhan', 'Erlangga', 2022, 'R-06', '#14b8a6', '#5eead4'),
            $this->buku('Cerita', 'Kisah Pahlawan Muda', 'Bambang Setiawan', 'Pustaka Anak', 2019, 'R-06', '#8b5cf6', '#c4b5fd'),

            // Sains
            $this->buku('Sains', 'Ensiklopedia Sains untuk Pelajar', 'Dr. Hendra Wijaya', 'Tiga Serangkai', 2020, 'R-07', '#0f766e', '#99f6e4'),
            $this->buku('Sains', 'Rahasia Tata Surya', 'Nurul Aini', 'Bumi Aksara', 2021, 'R-07', '#1e40af', '#bfdbfe'),
            $this->buku('Sains', 'Ekosistem dan Kehidupan', 'Prof. Siti Rahayu', 'Erlangga', 2018, 'R-07', '#166534', '#bbf7d0'),
            $this->buku('Sains', 'Eksperimen Sains Sederhana', 'Budi Kurniawan', 'Andi Publisher', 2022, 'R-07', '#9a3412', '#fed7aa'),

            // Sejarah
            $this->buku('Sejarah', 'Sejarah Indonesia Modern', 'Drs. Ahmad Fauzi', 'Yudhistira', 2019, 'R-08', '#7c2d12', '#fdba74'),
            $this->buku('Sejarah', 'Kerajaan Nusantara', 'Rudi Hartono', 'Balai Pustaka', 2017, 'R-08', '#a16207', '#fde68a'),
            $this->buku('Sejarah', 'Biografi Tokoh Pendidikan Indonesia', 'Lestari Ningsih', 'Pustaka Pelajar', 2020, 'R-08', '#4d7c0f', '#d9f99d'),

            // Teknologi
            $this->buku('Teknologi', 'Dasar-Dasar Pemrograman', 'Rizky Maulana, S.Kom.', 'Informatika', 2023, 'R-09', '#1d4ed8', '#bfdbfe'),
            $this->buku('Teknologi', 'Internet Sehat dan Aman', 'Deni Firmansyah', 'Elex Media', 2021, 'R-09', '#0e7490', '#a5f3fc'),
            $this->buku('Teknologi', 'Robotika untuk Pemula', 'Agus Setiawan', 'Andi Publisher', 2022, 'R-09', '#6d28d9', '#ddd6fe'),

            // Bahasa
            $this->buku('Bahasa', 'Kamus Istilah Bahasa Indonesia', 'Tim Bahasa', 'Balai Pustaka', 2016, 'R-10', '#be123c', '#fecdd3'),
            $this->buku('Bahasa', 'Percakapan Bahasa Inggris Sehari-hari', 'English Club Team', 'Grafindo', 2020, 'R-10', '#4338ca', '#c7d2fe'),

            // Seni dan Budaya
            $this->buku('Seni dan Budaya', 'Seni Tari Tradisional Nusantara', 'Wulan Sari, S.Pd.', 'Pustaka Budaya', 2019, 'R-11', '#a21caf', '#f5d0fe'),
            $this->buku('Seni dan Budaya', 'Alat Musik Daerah Indonesia', 'Gita Permata', 'Pustaka Budaya', 2018, 'R-11', '#c2410c', '#fed7aa'),

            // Agama
            $this->buku('Agama', 'Akhlak dan Kisah Teladan', 'Ust. Dedi Rachman', 'Pustaka Anak', 2021, 'R-12', '#047857', '#a7f3d0'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buku(
        string $kategori,
        string $judul,
        string $pengarang,
        string $penerbit,
        int $tahun,
        string $rak,
        string $warna,
        string $aksen
    ): array {
        return compact('kategori', 'judul', 'pengarang', 'penerbit', 'tahun', 'rak', 'warna', 'aksen');
    }

    private function pastikanKelas(string $nama, string $tingkat): int
    {
        $id = DB::table('kelas')->where('nama_kelas', $nama)->value('id_kelas');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('kelas')->insertGetId([
            'nama_kelas' => $nama,
            'tingkat' => $tingkat,
            'wali_kelas' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * Sampul SVG sederhana agar buku dummy tampil rapi di katalog.
     */
    private function svgCover(
        string $judul,
        string $pengarang,
        string $kategori,
        string $warna,
        string $aksen
    ): string {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $judulSvg = '';
        $y = 150;

        foreach ($this->wrap($judul, 18, 5) as $baris) {
            $judulSvg .= '<text x="26" y="'.$y.'" font-family="Georgia, Times New Roman, serif" '
                .'font-size="25" font-weight="700" fill="#ffffff">'.$e($baris).'</text>';
            $y += 34;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400" viewBox="0 0 300 400">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            .'<stop offset="0" stop-color="'.$warna.'"/>'
            .'<stop offset="1" stop-color="'.$aksen.'"/>'
            .'</linearGradient></defs>'
            .'<rect width="300" height="400" fill="url(#g)"/>'
            .'<circle cx="52" cy="62" r="72" fill="#ffffff" opacity="0.10"/>'
            .'<circle cx="262" cy="338" r="92" fill="#ffffff" opacity="0.10"/>'
            .'<rect x="24" y="26" width="150" height="26" rx="13" fill="#ffffff" opacity="0.22"/>'
            .'<text x="99" y="44" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" '
            .'font-size="11" font-weight="700" letter-spacing="1" fill="#ffffff">'.$e(mb_strtoupper($kategori)).'</text>'
            .$judulSvg
            .'<line x1="26" y1="'.($y + 2).'" x2="92" y2="'.($y + 2).'" stroke="#ffffff" '
            .'stroke-width="3" stroke-linecap="round" opacity="0.85"/>'
            .'<text x="26" y="352" font-family="Segoe UI, Arial, sans-serif" font-size="15" '
            .'font-weight="600" fill="#ffffff">'.$e($pengarang).'</text>'
            .'<text x="26" y="382" font-family="Segoe UI, Arial, sans-serif" font-size="11" '
            .'fill="#ffffff" opacity="0.8">SIPUS - Koleksi Perpustakaan</text>'
            .'</svg>';
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $teks, int $maks, int $maksBaris): array
    {
        $kata = preg_split('/\s+/', trim($teks)) ?: [];
        $baris = [];
        $sekarang = '';

        foreach ($kata as $k) {
            if ($sekarang === '') {
                $sekarang = $k;
            } elseif (mb_strlen($sekarang.' '.$k) <= $maks) {
                $sekarang .= ' '.$k;
            } else {
                $baris[] = $sekarang;
                $sekarang = $k;
            }
        }

        if ($sekarang !== '') {
            $baris[] = $sekarang;
        }

        return array_slice($baris, 0, $maksBaris);
    }
}

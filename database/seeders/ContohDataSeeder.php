<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ContohDataSeeder extends Seeder
{
    /**
     * Seed data contoh lengkap SIPUS.
     *
     * Membuat data untuk SEMUA tabel aplikasi:
     * t_user (Admin, Pustakawan, Siswa), kategori_buku, kelas,
     * siswa (beserta biodata), buku (beserta sampul),
     * peminjaman, detail_peminjaman, dan pengembalian.
     *
     * Seeder bersifat destruktif: seluruh data aplikasi lama dihapus
     * lalu dibuat ulang, sehingga cocok dipakai bersama `migrate:fresh --seed`.
     */
    public function run(): void
    {
        // Angka acak deterministik -> hasil seed selalu sama saat diulang.
        mt_srand(2026);

        $this->bersihkan();

        $today = Carbon::today()->startOfDay();

        // 1. Kategori buku
        $kategoriIds = [];
        foreach ([
            ['nama_kategori' => 'Pelajaran',      'keterangan' => 'Buku paket SMP (Kurikulum Merdeka)'],
            ['nama_kategori' => 'Cerita',         'keterangan' => 'Buku cerita, novel, dan dongeng anak'],
            ['nama_kategori' => 'Pelajaran Lain', 'keterangan' => 'Buku pendamping mata pelajaran (PJOK, Seni Budaya, Prakarya, Bahasa Jawa)'],
        ] as $k) {
            $kategoriIds[$k['nama_kategori']] = DB::table('kategori_buku')->insertGetId($k);
        }

        // 2. Buku + sampul
        Storage::disk('public')->makeDirectory('covers');

        $bukuIds   = [];
        $ceritaIds = [];

        foreach ($this->daftarBuku() as $i => $b) {
            $kode = 'SMP-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            $id = DB::table('buku')->insertGetId([
                'id_kategori'  => $kategoriIds[$b['kategori']],
                'no_seri_buku' => $kode,
                'judul'        => $b['judul'],
                'pengarang'    => $b['pengarang'],
                'penerbit'     => $b['penerbit'],
                'tahun_terbit' => $b['tahun'],
                'kategori'     => $b['kategori'],
                'lokasi_rak'   => $b['rak'],
                'stok'         => mt_rand(4, 10),
            ]);

            $bukuIds[] = $id;
            if ($b['kategori'] === 'Cerita') {
                $ceritaIds[] = $id;
            }

            $file = 'covers/' . $kode . '.svg';
            Storage::disk('public')->put(
                $file,
                $b['kategori'] === 'Cerita' ? $this->svgCerita($b) : $this->svgPelajaran($b)
            );
            DB::table('buku')->where('id_buku', $id)->update(['cover' => $file]);
        }

        // 3. Kelas (dipakai untuk relasi & form data anggota)
        foreach ([
            ['nama_kelas' => 'VII A',  'tingkat' => 'VII',  'wali_kelas' => 'Siti Nurhaliza, S.Pd.'],
            ['nama_kelas' => 'VII B',  'tingkat' => 'VII',  'wali_kelas' => 'Ahmad Fauzi, S.Pd.'],
            ['nama_kelas' => 'VIII A', 'tingkat' => 'VIII', 'wali_kelas' => 'Dewi Kartika, M.Pd.'],
            ['nama_kelas' => 'VIII B', 'tingkat' => 'VIII', 'wali_kelas' => 'Budi Santoso, S.Pd.'],
            ['nama_kelas' => 'IX A',   'tingkat' => 'IX',   'wali_kelas' => 'Rina Marlina, S.Pd.'],
        ] as $k) {
            DB::table('kelas')->insert($k + ['created_at' => now()]);
        }

        // 4. Akun pengelola: Admin & Pustakawan
        foreach ([
            ['name' => 'Administrator SIPUS',     'username' => 'admin67@sipus.sch.id',      'password' => '87654321', 'role' => 'Admin'],
            ['name' => 'Rina Kusuma, S.IP.',      'username' => 'pustakawan54@sipus.sch.id', 'password' => '34567890', 'role' => 'Pustakawan'],
        ] as $a) {
            DB::table('t_user')->insert([
                'name'     => $a['name'],
                'username' => $a['username'],
                'password' => Hash::make($a['password']),
                'role'     => $a['role'],
            ]);
        }

        // 5. Siswa + akun login (lengkap dengan biodata)
        // Username opsional: bila null, dipakai "<nis>@siswa.sipus.sch.id".
        $siswaIds = [];
        foreach ([
            ['Aulia Diani',    'VII A',  'siswa34@sipus.sch.id', 'Laki-laki', 'Bandung',      '2011-03-14'],
            ['Aditya Pratama', 'VII A',  null,                  'Laki-laki', 'Cimahi',       '2011-07-02'],
            ['Bunga Lestari',  'VII A',  null,                  'Perempuan', 'Garut',        '2011-11-21'],
            ['Citra Ayu',      'VII B',  null,                  'Perempuan', 'Sumedang',     '2011-05-09'],
            ['Dimas Saputra',  'VIII A', null,                  'Laki-laki', 'Bandung',      '2010-02-18'],
            ['Eka Wulandari',  'VIII A', null,                  'Perempuan', 'Subang',       '2010-08-30'],
            ['Fajar Nugroho',  'VIII B', null,                  'Laki-laki', 'Bandung Barat','2010-12-05'],
            ['Gita Permata',   'IX A',   null,                  'Perempuan', 'Bandung',      '2009-04-27'],
            ['Hendra Wijaya',  'IX A',   null,                  'Laki-laki', 'Cianjur',      '2009-09-16'],
        ] as $i => $s) {
            $nis      = '2024' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $username = $s[2] ?? ($nis . '@siswa.sipus.sch.id');

            $idUser = DB::table('t_user')->insertGetId([
                'username' => $username,
                'password' => Hash::make('12345678'),
                'role'     => 'Siswa',
            ]);

            $siswaIds[] = DB::table('siswa')->insertGetId([
                'id_user'       => $idUser,
                'nis'           => $nis,
                'nama_siswa'    => $s[0],
                'kelas'         => $s[1],
                'jenis_kelamin' => $s[3],
                'tempat_lahir'  => $s[4],
                'tanggal_lahir' => $s[5],
                'alamat'        => 'Jl. Pendidikan No. ' . ($i + 1) . ', Bandung',
                'no_telp'       => '0812' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            ]);
        }

        // Pool berbobot: buku cerita lebih sering dipinjam.
        $bukuPool = array_merge($bukuIds, $ceritaIds, $ceritaIds);

        // 6. Peminjaman + detail + pengembalian (tersebar 45 hari terakhir)
        $totalPinjam   = 0;
        $totalKembali  = 0;

        for ($i = 0; $i < 45; $i++) {
            $tanggal = $today->copy()->subDays(mt_rand(0, 44));
            $jatuh   = $tanggal->copy()->addDays(7);
            $siswaId = $siswaIds[array_rand($siswaIds)];

            $r = mt_rand(1, 100);
            $kembali = null;

            if ($r <= 8) {
                $status = 'Menunggu';
            } elseif ($r <= 18) {
                $status = 'Ditolak';
            } elseif ($jatuh->lt($today)) {
                $status = 'Dipinjam';
                if ($r <= 80) {
                    $kembali = null; // masih terlambat, belum dikembalikan
                } else {
                    $kembali = $jatuh->copy()->addDays(mt_rand(1, 5)); // dikembalikan telat
                    if ($kembali->gt($today)) {
                        $kembali = $today->copy();
                    }
                }
            } else {
                $status = 'Dipinjam';
                if ($r <= 60) {
                    $kembali = null; // masih dipinjam
                } else {
                    $kembali = $tanggal->copy()->addDays(mt_rand(2, 6)); // kembali tepat waktu
                }
            }

            $idP = DB::table('peminjaman')->insertGetId([
                'id_siswa'            => $siswaId,
                'tanggal_pinjam'      => $tanggal->format('Y-m-d'),
                'tanggal_jatuh_tempo' => $jatuh->format('Y-m-d'),
                'status'              => $status,
            ]);
            $totalPinjam++;

            $jumlahJudul = mt_rand(1, 2);
            $terpilih = [];
            while (count($terpilih) < $jumlahJudul) {
                $idBuku = $bukuPool[array_rand($bukuPool)];
                $terpilih[$idBuku] = true;
            }
            foreach (array_keys($terpilih) as $idBuku) {
                DB::table('detail_peminjaman')->insert([
                    'id_peminjaman' => $idP,
                    'id_buku'       => $idBuku,
                    'jumlah'        => mt_rand(1, 2),
                ]);
            }

            if ($kembali !== null) {
                $telat = $kembali->gt($jatuh) ? (int) $jatuh->diffInDays($kembali) : 0;
                $denda = $telat * 500;

                // Denda 0 -> otomatis Lunas; denda > 0 -> campuran Lunas / Belum Lunas.
                $statusBayar = ($denda === 0 || mt_rand(0, 1) === 1) ? 'Lunas' : 'Belum Lunas';

                DB::table('pengembalian')->insert([
                    'id_peminjaman'   => $idP,
                    'tanggal_kembali' => $kembali->format('Y-m-d'),
                    'denda'           => $denda,
                    'kategori'        => $telat > 0 ? 'Terlambat' : 'Tepat Waktu',
                    'status_bayar'    => $statusBayar,
                ]);
                $totalKembali++;
            }
        }

        $this->command?->info(
            'Seed lengkap dibuat: '
            . count($bukuIds) . ' buku (' . count($ceritaIds) . ' cerita), '
            . count($siswaIds) . ' siswa, 5 kelas, 2 akun pengelola, '
            . $totalPinjam . ' peminjaman, ' . $totalKembali . ' pengembalian.'
        );
    }

    /**
     * Kosongkan seluruh tabel aplikasi & sampul lama.
     */
    private function bersihkan(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'pengembalian',
            'detail_peminjaman',
            'peminjaman',
            'siswa',
            't_user',
            'buku',
            'kategori_buku',
            'kelas',
        ] as $tabel) {
            DB::table($tabel)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Storage::disk('public')->deleteDirectory('covers');
    }

    private function daftarBuku(): array
    {
        $buku = [];

        // 8 buku paket wajib untuk setiap tingkat: VII, VIII, dan IX.
        // mapel, sub (Inggris), warna, aksen, awalan
        $mapel = [
            ['Pendidikan Agama Islam dan Budi Pekerti', 'Islamic Education', '#0f7a4d', '#f4c542', 'PAI'],
            ['Pendidikan Pancasila', 'Pancasila Education', '#b91c1c', '#fca5a5', 'PKN'],
            ['Bahasa Indonesia', 'Indonesian Language', '#c2410c', '#fdba74', 'BIN'],
            ['Matematika', 'Mathematics', '#1d4ed8', '#93c5fd', 'MTK'],
            ['Ilmu Pengetahuan Alam', 'Natural Science', '#15803d', '#86efac', 'IPA'],
            ['Ilmu Pengetahuan Sosial', 'Social Studies', '#b45309', '#fcd34d', 'IPS'],
            ['Bahasa Inggris', 'English', '#6d28d9', '#c4b5fd', 'ENG'],
            ['Informatika', 'Informatics', '#7c3aed', '#ddd6fe', 'INF'],
        ];

        foreach (['VII', 'VIII', 'IX'] as $kelas) {
            foreach ($mapel as $m) {
                $buku[] = [
                    'kategori'  => 'Pelajaran',
                    'judul'     => $m[0] . ' Kelas ' . $kelas,
                    'sampul'    => $m[0],
                    'sub'       => $m[1],
                    'kelas'     => $kelas,
                    'warna'     => $m[2],
                    'aksen'     => $m[3],
                    'awalan'    => $m[4],
                    'pengarang' => 'Tim Penyusun',
                    'penerbit'  => 'Kemendikbudristek',
                    'tahun'     => 2022,
                    'rak'       => 'R-01',
                ];
            }
        }

        // Buku pendamping (tetap disimpan agar riwayat peminjaman lama tidak hilang).
        foreach ([
            ['Pendidikan Jasmani, Olahraga, dan Kesehatan', 'Physical Education', '#0e7490', '#67e8f9', 'PJOK'],
            ['Seni Budaya', 'Arts and Culture', '#be185d', '#f9a8d4', 'SENI'],
            ['Prakarya', 'Crafts', '#a16207', '#fde68a', 'PRK'],
            ['Bahasa Jawa', 'Javanese Language', '#78350f', '#fcd34d', 'BJW'],
        ] as $p) {
            $buku[] = [
                'kategori'  => 'Pelajaran Lain',
                'judul'     => $p[0] . ' Kelas VII',
                'sampul'    => $p[0],
                'sub'       => $p[1],
                'kelas'     => 'VII',
                'warna'     => $p[2],
                'aksen'     => $p[3],
                'awalan'    => $p[4],
                'pengarang' => 'Tim Penyusun',
                'penerbit'  => 'Kemendikbudristek',
                'tahun'     => 2022,
                'rak'       => 'R-01',
            ];
        }

        // judul, pengarang, penerbit, tahun, warna, aksen
        $cerita = [
            ['Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, '#f97316', '#fbbf24'],
            ['Negeri 5 Menara', 'Ahmad Fuadi', 'Gramedia', 2009, '#0ea5e9', '#22d3ee'],
            ['Rindu', 'Tere Liye', 'Republika', 2014, '#ef4444', '#fca5a5'],
            ['Hujan', 'Tere Liye', 'Gramedia', 2016, '#6366f1', '#a5b4fc'],
            ['Perahu Kertas', 'Dee Lestari', 'Bentang Pustaka', 2009, '#14b8a6', '#5eead4'],
            ['Kumpulan Dongeng Nusantara', 'Tim Cerita Rakyat', 'Balai Pustaka', 2010, '#10b981', '#6ee7b7'],
            ['Malin Kundang dan Cerita Rakyat Lainnya', 'Tim Penyusun', 'Erlangga', 2015, '#8b5cf6', '#c4b5fd'],
            ['Sangkuriang: Legenda Tangkuban Perahu', 'Tim Penyusun', 'Yudhistira', 2016, '#f59e0b', '#fcd34d'],
            ['Timun Mas dan Kisah Ajaib Lainnya', 'Tim Penyusun', 'Erlangga', 2015, '#22c55e', '#bbf7d0'],
            ['Petualangan Tom Sawyer', 'Mark Twain', 'Atria', 2013, '#3b82f6', '#93c5fd'],
            ['Kisah 25 Nabi dan Rasul', 'Dedi Rachman', 'Pustaka Anak', 2018, '#0d9488', '#5eead4'],
            ['Si Kancil yang Cerdik', 'Tim Penyusun', 'Balai Pustaka', 2012, '#eab308', '#fef08a'],
        ];

        foreach ($cerita as $c) {
            $buku[] = [
                'kategori'  => 'Cerita',
                'judul'     => $c[0],
                'sampul'    => $c[0],
                'warna'     => $c[4],
                'aksen'     => $c[5],
                'pengarang' => $c[1],
                'penerbit'  => $c[2],
                'tahun'     => $c[3],
                'rak'       => 'R-02',
            ];
        }

        return $buku;
    }

    private function svgPelajaran(array $b): string
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $titleSvg = '';
        $y = 268;
        foreach ($this->wrap($b['sampul'], 16, 4) as $line) {
            $titleSvg .= '<text x="24" y="' . $y . '" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="800" fill="#ffffff">' . $e($line) . '</text>';
            $y += 30;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400" viewBox="0 0 300 400">'
            . '<rect width="300" height="400" fill="' . $b['warna'] . '"/>'
            . '<rect width="300" height="190" fill="#ffffff"/>'
            . '<circle cx="238" cy="52" r="86" fill="' . $b['warna'] . '" opacity="0.12"/>'
            . '<circle cx="58" cy="170" r="46" fill="' . $b['aksen'] . '" opacity="0.35"/>'
            . '<text x="24" y="36" font-family="Segoe UI, Arial, sans-serif" font-size="13" font-weight="800" letter-spacing="1.5" fill="' . $b['warna'] . '">KURIKULUM MERDEKA</text>'
            . '<text x="150" y="132" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="64" font-weight="800" fill="' . $b['warna'] . '">' . $e($b['awalan']) . '</text>'
            . '<text x="150" y="172" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="600" fill="' . $b['warna'] . '" opacity="0.75">' . $e($b['sub']) . '</text>'
            . '<text x="24" y="228" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="1" fill="#ffffff" opacity="0.9">SMP/MTs  ·  KELAS ' . $e($b['kelas']) . '</text>'
            . $titleSvg
            . '<text x="24" y="384" font-family="Segoe UI, Arial, sans-serif" font-size="11" fill="#ffffff" opacity="0.75">' . $e($b['penerbit']) . '</text>'
            . '</svg>';
    }

    private function svgCerita(array $b): string
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $titleSvg = '';
        $y = 150;
        foreach ($this->wrap($b['sampul'], 13, 4) as $line) {
            $titleSvg .= '<text x="28" y="' . $y . '" font-family="Georgia, Times New Roman, serif" font-size="26" font-weight="700" fill="#ffffff">' . $e($line) . '</text>';
            $y += 36;
        }
        $dividerY = $y + 4;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400" viewBox="0 0 300 400">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="' . $b['warna'] . '"/><stop offset="1" stop-color="' . $b['aksen'] . '"/>'
            . '</linearGradient></defs>'
            . '<rect width="300" height="400" fill="url(#g)"/>'
            . '<circle cx="46" cy="70" r="66" fill="#ffffff" opacity="0.08"/>'
            . '<circle cx="262" cy="330" r="90" fill="#ffffff" opacity="0.08"/>'
            . '<rect x="24" y="26" width="126" height="26" rx="13" fill="#ffffff" opacity="0.22"/>'
            . '<text x="87" y="44" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="1" fill="#ffffff">BUKU CERITA</text>'
            . $titleSvg
            . '<line x1="28" y1="' . $dividerY . '" x2="96" y2="' . $dividerY . '" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.85"/>'
            . '<text x="28" y="352" font-family="Segoe UI, Arial, sans-serif" font-size="15" font-weight="600" fill="#ffffff" opacity="0.95">' . $e($b['pengarang']) . '</text>'
            . '<text x="28" y="382" font-family="Segoe UI, Arial, sans-serif" font-size="11" fill="#ffffff" opacity="0.75">' . $e($b['penerbit']) . '</text>'
            . '</svg>';
    }

    private function wrap(string $text, int $max, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $cur = '';

        foreach ($words as $w) {
            if ($cur === '') {
                $cur = $w;
            } elseif (mb_strlen($cur . ' ' . $w) <= $max) {
                $cur .= ' ' . $w;
            } else {
                $lines[] = $cur;
                $cur = $w;
            }
        }

        if ($cur !== '') {
            $lines[] = $cur;
        }

        return array_slice($lines, 0, $maxLines);
    }
}

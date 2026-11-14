<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PerbaikiSampulBuku extends Command
{
    protected $signature = 'sipus:perbaiki-sampul
                            {--dry-run : Tampilkan buku yang akan dibuat ulang sampulnya tanpa menulis file}';

    protected $description = 'Membuat ulang sampul buku agar judul, pengarang, dan kategorinya sesuai data buku';

    /**
     * Palet warna dan identitas setiap mata pelajaran.
     *
     * @var array<string, array{kode: string, sub: string, warna: string, aksen: string}>
     */
    private array $mapel = [
        'Pendidikan Agama Islam' => ['kode' => 'PAI', 'sub' => 'Islamic Education', 'warna' => '#0f7a4d', 'aksen' => '#f4c542'],
        'Pendidikan Pancasila'   => ['kode' => 'PKN', 'sub' => 'Pancasila Education', 'warna' => '#b91c1c', 'aksen' => '#fca5a5'],
        'Bahasa Indonesia'       => ['kode' => 'BIN', 'sub' => 'Indonesian Language', 'warna' => '#c2410c', 'aksen' => '#fdba74'],
        'Matematika'             => ['kode' => 'MTK', 'sub' => 'Mathematics', 'warna' => '#1d4ed8', 'aksen' => '#93c5fd'],
        'Ilmu Pengetahuan Alam'  => ['kode' => 'IPA', 'sub' => 'Natural Science', 'warna' => '#15803d', 'aksen' => '#86efac'],
        'Ilmu Pengetahuan Sosial' => ['kode' => 'IPS', 'sub' => 'Social Studies', 'warna' => '#b45309', 'aksen' => '#fcd34d'],
        'Bahasa Inggris'         => ['kode' => 'ENG', 'sub' => 'English', 'warna' => '#6d28d9', 'aksen' => '#c4b5fd'],
        'Informatika'            => ['kode' => 'INF', 'sub' => 'Informatics', 'warna' => '#7c3aed', 'aksen' => '#ddd6fe'],
        'Pendidikan Jasmani'     => ['kode' => 'PJOK', 'sub' => 'Physical Education', 'warna' => '#0e7490', 'aksen' => '#67e8f9'],
        'Seni Budaya'            => ['kode' => 'SENI', 'sub' => 'Arts and Culture', 'warna' => '#be185d', 'aksen' => '#f9a8d4'],
        'Prakarya'               => ['kode' => 'PRK', 'sub' => 'Crafts', 'warna' => '#a16207', 'aksen' => '#fde68a'],
        'Bahasa Sunda'           => ['kode' => 'SUN', 'sub' => 'Sundanese Language', 'warna' => '#4338ca', 'aksen' => '#c7d2fe'],
        'Bahasa Jawa'            => ['kode' => 'BJW', 'sub' => 'Javanese Language', 'warna' => '#78350f', 'aksen' => '#fcd34d'],
    ];

    /**
     * Palet warna buku cerita yang sudah dikenal.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $paletCerita = [
        'Laskar Pelangi'                         => ['#f97316', '#fbbf24'],
        'Negeri 5 Menara'                        => ['#0ea5e9', '#22d3ee'],
        'Rindu'                                  => ['#ef4444', '#fca5a5'],
        'Hujan'                                  => ['#6366f1', '#a5b4fc'],
        'Perahu Kertas'                          => ['#14b8a6', '#5eead4'],
        'Kumpulan Dongeng Nusantara'             => ['#10b981', '#6ee7b7'],
        'Malin Kundang dan Cerita Rakyat Lainnya' => ['#8b5cf6', '#c4b5fd'],
        'Sangkuriang: Legenda Tangkuban Perahu'  => ['#f59e0b', '#fcd34d'],
        'Timun Mas dan Kisah Ajaib Lainnya'      => ['#22c55e', '#bbf7d0'],
        'Petualangan Tom Sawyer'                 => ['#3b82f6', '#93c5fd'],
        'Kisah 25 Nabi dan Rasul'                => ['#0d9488', '#5eead4'],
        'Si Kancil yang Cerdik'                  => ['#eab308', '#fef08a'],
    ];

    /**
     * Palet cadangan untuk buku cerita yang belum terdaftar di atas.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private array $paletCadangan = [
        ['#f97316', '#fbbf24'],
        ['#0ea5e9', '#22d3ee'],
        ['#ef4444', '#fca5a5'],
        ['#6366f1', '#a5b4fc'],
        ['#14b8a6', '#5eead4'],
        ['#10b981', '#6ee7b7'],
        ['#8b5cf6', '#c4b5fd'],
        ['#f59e0b', '#fcd34d'],
        ['#3b82f6', '#93c5fd'],
        ['#eab308', '#fef08a'],
    ];

    public function handle(): int
    {
        $buku = DB::table('buku as b')
            ->leftJoin('kategori_buku as k', 'k.id_kategori', '=', 'b.id_kategori')
            ->select('b.*', 'k.nama_kategori')
            ->orderBy('b.id_buku')
            ->get();

        if ($buku->isEmpty()) {
            $this->warn('Tidak ada data buku yang perlu diperbaiki.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $jumlah = 0;

        if (! $dryRun) {
            Storage::disk('public')->makeDirectory('covers');
        }

        foreach ($buku as $row) {
            $kategori = $row->nama_kategori ?: $row->kategori;
            $path = $row->cover ?: ($row->no_seri_buku ? 'covers/' . $row->no_seri_buku . '.svg' : null);

            if (! $path) {
                $this->warn("Buku #{$row->id_buku} dilewati: tidak memiliki path sampul maupun nomor seri.");

                continue;
            }

            $isi = $kategori === 'Cerita'
                ? $this->svgCerita($row)
                : $this->svgPelajaran($row);

            if ($dryRun) {
                $this->line("#{$row->id_buku} [{$kategori}] {$row->judul} -> {$path}");
                $jumlah++;

                continue;
            }

            Storage::disk('public')->put($path, $isi);

            if ($row->cover !== $path) {
                DB::table('buku')->where('id_buku', $row->id_buku)->update(['cover' => $path]);
            }

            $jumlah++;
        }

        $this->info(($dryRun ? 'Akan diperbaiki' : 'Sampul diperbaiki') . ": {$jumlah} buku.");

        return self::SUCCESS;
    }

    private function svgPelajaran(object $buku): string
    {
        $e = fn (string $teks) => htmlspecialchars($teks, ENT_QUOTES, 'UTF-8');

        $judul = trim((string) $buku->judul);
        $mapel = $this->identitasMapel($judul);
        $kelas = preg_match('/Kelas\s+(XII|XI|IX|VIII|VII|X)\b/i', $judul, $cocok)
            ? strtoupper($cocok[1])
            : '-';
        $namaMapel = trim((string) preg_replace('/\s+Kelas\s+(XII|XI|IX|VIII|VII|X)\b.*$/i', '', $judul));

        $titleSvg = '';
        $y = 268;
        foreach ($this->wrap($namaMapel, 16, 4) as $baris) {
            $titleSvg .= '<text x="24" y="' . $y . '" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="800" fill="#ffffff">' . $e($baris) . '</text>';
            $y += 30;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400" viewBox="0 0 300 400">'
            . '<rect width="300" height="400" fill="' . $mapel['warna'] . '"/>'
            . '<rect width="300" height="190" fill="#ffffff"/>'
            . '<circle cx="238" cy="52" r="86" fill="' . $mapel['warna'] . '" opacity="0.12"/>'
            . '<circle cx="58" cy="170" r="46" fill="' . $mapel['aksen'] . '" opacity="0.35"/>'
            . '<text x="24" y="36" font-family="Segoe UI, Arial, sans-serif" font-size="13" font-weight="800" letter-spacing="1.5" fill="' . $mapel['warna'] . '">KURIKULUM MERDEKA</text>'
            . '<text x="150" y="132" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="64" font-weight="800" fill="' . $mapel['warna'] . '">' . $e($mapel['kode']) . '</text>'
            . '<text x="150" y="172" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="600" fill="' . $mapel['warna'] . '" opacity="0.75">' . $e($mapel['sub']) . '</text>'
            . '<text x="24" y="228" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="1" fill="#ffffff" opacity="0.9">SMP/MTs  ·  KELAS ' . $e($kelas) . '</text>'
            . $titleSvg
            . '<text x="24" y="384" font-family="Segoe UI, Arial, sans-serif" font-size="11" fill="#ffffff" opacity="0.75">' . $e((string) $buku->penerbit) . '</text>'
            . '</svg>';
    }

    private function svgCerita(object $buku): string
    {
        $e = fn (string $teks) => htmlspecialchars($teks, ENT_QUOTES, 'UTF-8');

        $judul = trim((string) $buku->judul);
        [$warna, $aksen] = $this->paletCerita[$judul]
            ?? $this->paletCadangan[abs(crc32($judul)) % count($this->paletCadangan)];

        $titleSvg = '';
        $y = 150;
        foreach ($this->wrap($judul, 13, 4) as $baris) {
            $titleSvg .= '<text x="28" y="' . $y . '" font-family="Georgia, Times New Roman, serif" font-size="26" font-weight="700" fill="#ffffff">' . $e($baris) . '</text>';
            $y += 36;
        }
        $dividerY = $y + 4;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400" viewBox="0 0 300 400">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="' . $warna . '"/><stop offset="1" stop-color="' . $aksen . '"/>'
            . '</linearGradient></defs>'
            . '<rect width="300" height="400" fill="url(#g)"/>'
            . '<circle cx="46" cy="70" r="66" fill="#ffffff" opacity="0.08"/>'
            . '<circle cx="262" cy="330" r="90" fill="#ffffff" opacity="0.08"/>'
            . '<rect x="24" y="26" width="126" height="26" rx="13" fill="#ffffff" opacity="0.22"/>'
            . '<text x="87" y="44" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="1" fill="#ffffff">BUKU CERITA</text>'
            . $titleSvg
            . '<line x1="28" y1="' . $dividerY . '" x2="96" y2="' . $dividerY . '" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.85"/>'
            . '<text x="28" y="352" font-family="Segoe UI, Arial, sans-serif" font-size="15" font-weight="600" fill="#ffffff" opacity="0.95">' . $e((string) $buku->pengarang) . '</text>'
            . '<text x="28" y="382" font-family="Segoe UI, Arial, sans-serif" font-size="11" fill="#ffffff" opacity="0.75">' . $e((string) $buku->penerbit) . '</text>'
            . '</svg>';
    }

    /**
     * @return array{kode: string, sub: string, warna: string, aksen: string}
     */
    private function identitasMapel(string $judul): array
    {
        foreach ($this->mapel as $nama => $identitas) {
            if (stripos($judul, $nama) === 0) {
                return $identitas;
            }
        }

        return ['kode' => 'BUKU', 'sub' => 'Mata Pelajaran', 'warna' => '#334155', 'aksen' => '#cbd5e1'];
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $teks, int $maksKarakter, int $maksBaris): array
    {
        $kata = preg_split('/\s+/', trim($teks)) ?: [];
        $baris = [];
        $sekarang = '';

        foreach ($kata as $k) {
            if ($sekarang === '') {
                $sekarang = $k;
            } elseif (mb_strlen($sekarang . ' ' . $k) <= $maksKarakter) {
                $sekarang .= ' ' . $k;
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

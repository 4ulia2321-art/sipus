<?php

namespace App\Http\Controllers;

use App\Models\Buku;
use App\Models\KategoriBuku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KatalogController extends Controller
{
    /**
     * Halaman utama katalog: daftar kategori/kelas buku.
     */
    public function index()
    {
        // Katalog siswa menampilkan buku paket (Pelajaran) dan buku cerita.
        // Kategori "Pelajaran Lain" tetap ada di database, tetapi tidak ditampilkan di sini.
        $urutanKategori = ['Pelajaran' => 1, 'Cerita' => 2];

        $kategoriCards = KategoriBuku::whereIn('nama_kategori', array_keys($urutanKategori))
            ->get()
            ->sortBy(fn ($kategori) => $urutanKategori[$kategori->nama_kategori] ?? 99)
            ->values()
            ->map(function ($kategori) {
                $buku = Buku::where('id_kategori', $kategori->id_kategori);

                $kategori->jumlah = (clone $buku)->count();
                $kategori->cover  = (clone $buku)
                    ->whereNotNull('cover')
                    ->where('cover', '<>', '')
                    ->value('cover');

                return $kategori;
            });

        return view('katalog.index', compact('kategoriCards'));
    }

    /**
     * Daftar buku dalam satu kategori.
     * Khusus kategori "Pelajaran", buku dikelompokkan per kelas VII/VIII/IX.
     */
    public function showKategori(Request $request, $namaKategori)
    {
        $namaKategori = trim((string) $namaKategori);

        // Siswa hanya boleh melihat buku paket (Pelajaran) dan buku cerita.
        if ((auth()->user()->role ?? null) === 'Siswa'
            && ! in_array(strtolower($namaKategori), ['pelajaran', 'cerita'], true)) {
            return redirect()->route('katalog');
        }

        $query = DB::table('buku')
            ->selectRaw("id_buku, judul, pengarang, kategori, cover, CASE WHEN stok > 0 THEN 'tersedia' ELSE 'dipinjam' END as status")
            ->whereRaw('TRIM(kategori) = ?', [$namaKategori]);

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('judul', 'like', "%{$q}%")
                    ->orWhere('pengarang', 'like', "%{$q}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->whereRaw($status === 'tersedia' ? 'stok > 0' : 'stok <= 0');
        }

        [$kolom, $arah] = match ($request->input('urutan', 'terbaru')) {
            'judul'     => ['judul', 'asc'],
            'pengarang' => ['pengarang', 'asc'],
            default     => ['created_at', 'desc'],
        };

        $query->orderBy($kolom, $arah);

        if (strcasecmp($namaKategori, 'Pelajaran') === 0) {
            $bukuPerKelas = $this->kelompokkanPerKelas($query->get());

            return view('katalog.kategori', compact('namaKategori', 'bukuPerKelas'));
        }

        $bukuList = $query->paginate(9)->withQueryString();

        return view('katalog.kategori', compact('namaKategori', 'bukuList'));
    }

    /**
     * Kelompokkan buku paket berdasarkan kelas yang tertulis pada judul.
     * Urutan mapel dibuat tetap agar tampilan konsisten.
     */
    private function kelompokkanPerKelas($bukuList)
    {
        $urutanMapel = [
            'pendidikan agama islam dan budi pekerti' => 1,
            'pendidikan pancasila'                    => 2,
            'bahasa indonesia'                        => 3,
            'matematika'                              => 4,
            'ilmu pengetahuan alam'                   => 5,
            'ilmu pengetahuan sosial'                 => 6,
            'bahasa inggris'                          => 7,
            'informatika'                             => 8,
        ];

        $grouped = $bukuList->groupBy(
            fn ($buku) => $this->kelasDariJudul($buku->judul) ?? 'Tanpa Kelas'
        );

        $hasil = collect();

        foreach (['VII', 'VIII', 'IX', 'Tanpa Kelas'] as $kelas) {
            if (! $grouped->has($kelas)) {
                continue;
            }

            $hasil[$kelas] = $grouped->get($kelas)
                ->sortBy(function ($buku) use ($urutanMapel) {
                    $judul = mb_strtolower(trim($buku->judul));

                    foreach ($urutanMapel as $awalan => $urutan) {
                        if (str_starts_with($judul, $awalan)) {
                            return $urutan;
                        }
                    }

                    return 99;
                })
                ->values();
        }

        return $hasil;
    }

    /**
     * Ambil tingkat kelas dari judul buku, mis. "Matematika Kelas VIII" -> VIII.
     */
    private function kelasDariJudul(?string $judul): ?string
    {
        if (! preg_match('/\bKelas\s+(VIII|VII|IX|7|8|9)\b/i', (string) $judul, $match)) {
            return null;
        }

        return match (strtoupper($match[1])) {
            '7' => 'VII',
            '8' => 'VIII',
            '9' => 'IX',
            default => strtoupper($match[1]),
        };
    }

    /**
     * Detail satu buku.
     */
    public function show($id)
    {
        $buku = DB::table('buku')
            ->selectRaw("
                id_buku,
                id_buku as id,
                judul,
                pengarang,
                kategori,
                tahun_terbit,
                lokasi_rak,
                cover,
                NULL as jumlah_halaman,
                NULL as sinopsis,
                CASE WHEN stok > 0 THEN 'tersedia' ELSE 'dipinjam' END as status
            ")
            ->where('id_buku', $id)
            ->first();

        abort_if(! $buku, 404);

        return view('katalog.show', compact('buku'));
    }

    /**
     * Siswa mengajukan pengembalian / menyerahkan buku kepada pustakawan.
     */
    public function pengembalian($id)
    {
        return back()->with('success', 'Pengajuan pengembalian akan segera diproses petugas.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\NotifikasiSiswa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotifikasiController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isStaff = in_array($user->role, ['Admin', 'Pustakawan'], true);
        $items = [];

        $idSiswa = $isStaff
            ? null
            : DB::table('siswa')->where('id_user', $user->id_user)->value('id_siswa');

        // Tautan notifikasi staf mengikuti area masing-masing role.
        $tautanStaf = $user->role === 'Pustakawan'
            ? route('pustakawan.pengajuan')
            : route('admin.laporan');

        if ($isStaff) {
            $menunggu = DB::table('peminjaman')->where('status', 'Menunggu')->count();

            if ($menunggu > 0) {
                $items[] = [
                    'jenis' => 'pengajuan',
                    'judul' => 'Menunggu Persetujuan',
                    'pesan' => "Terdapat {$menunggu} pengajuan peminjaman yang belum disetujui.",
                    'waktu' => Carbon::now(),
                    'url'   => $tautanStaf,
                ];
            }

            // Pengingat jatuh tempo seluruh anggota (tampilan staf).
            $hampirJatuhTempo = DB::table('peminjaman')
                ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
                ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
                ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
                ->where('peminjaman.status', 'Dipinjam')
                ->whereNull('pengembalian.id_pengembalian')
                ->whereBetween('peminjaman.tanggal_jatuh_tempo', [Carbon::today()->toDateString(), Carbon::today()->addDays(3)->toDateString()])
                ->pluck('buku.judul');

            if ($hampirJatuhTempo->isNotEmpty()) {
                $items[] = [
                    'jenis' => 'jatuh_tempo',
                    'judul' => 'Pengingat Jatuh Tempo',
                    'pesan' => 'Peminjaman yang akan jatuh tempo dalam 3 hari: ' . $hampirJatuhTempo->join(', ') . '.',
                    'waktu' => Carbon::today(),
                    'url'   => $tautanStaf,
                ];
            }

            $terlambat = DB::table('peminjaman')
                ->join('detail_peminjaman', 'detail_peminjaman.id_peminjaman', '=', 'peminjaman.id_peminjaman')
                ->join('buku', 'buku.id_buku', '=', 'detail_peminjaman.id_buku')
                ->leftJoin('pengembalian', 'pengembalian.id_peminjaman', '=', 'peminjaman.id_peminjaman')
                ->where('peminjaman.status', 'Dipinjam')
                ->whereNull('pengembalian.id_pengembalian')
                ->where('peminjaman.tanggal_jatuh_tempo', '<', Carbon::today()->toDateString())
                ->pluck('buku.judul');

            if ($terlambat->isNotEmpty()) {
                $items[] = [
                    'jenis' => 'terlambat',
                    'judul' => 'Terlambat Mengembalikan',
                    'pesan' => 'Peminjaman yang melewati jatuh tempo: ' . $terlambat->join(', ') . '. Segera tindak lanjuti.',
                    'waktu' => Carbon::today(),
                    'url'   => $tautanStaf,
                ];
            }
        } else {
            // Notifikasi terlambat / akan jatuh tempo beserta estimasi denda.
            $items = $idSiswa
                ? NotifikasiSiswa::untukSiswa((int) $idSiswa)->all()
                : [];

            // Peminjaman berhasil: buku yang sedang dipinjam (pengajuan disetujui).
            $berhasil = $idSiswa
                ? DB::table('peminjaman as p')
                    ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'p.id_peminjaman')
                    ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                    ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
                    ->where('p.id_siswa', $idSiswa)
                    ->where('p.status', 'Dipinjam')
                    ->whereNull('pg.id_pengembalian')
                    ->orderByDesc('p.tanggal_pinjam')
                    ->pluck('b.judul')
                : collect();

            if ($berhasil->isNotEmpty()) {
                $items[] = [
                    'jenis' => 'peminjaman',
                    'judul' => 'Peminjaman Berhasil',
                    'pesan' => 'Peminjaman buku ' . $berhasil->join(', ') . ' telah disetujui. Silakan ambil buku di perpustakaan.',
                    'waktu' => Carbon::now(),
                    'url'   => route('riwayat'),
                ];
            }

            // Informasi sistem.
            $items[] = [
                'jenis' => 'sistem',
                'judul' => 'Informasi Sistem',
                'pesan' => 'Selamat datang di SIPUS. Kembalikan buku tepat waktu untuk menghindari denda keterlambatan.',
                'waktu' => Carbon::now(),
                'url'   => route('tentang'),
            ];
        }

        $notifikasi = collect($items)->map(fn ($n) => (object) [
            'jenis'            => $n['jenis'],
            'judul'            => $n['judul'],
            'pesan'            => $n['pesan'],
            'waktu'            => $n['waktu'],
            'url'              => $n['url'],
            'dibaca'           => false,
            'denda'            => (int) ($n['denda'] ?? 0),
            'keterangan_denda' => $n['keterangan_denda'] ?? null,
        ]);

        return view($isStaff ? 'admin.notifikasi.index' : 'notifikasi.index', compact('notifikasi'));
    }

    public function bacaSemua()
    {
        return back()->with('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    }
}

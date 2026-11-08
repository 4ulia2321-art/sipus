<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PengaturanController extends Controller
{
    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()->role === 'Admin', 403);
    }

    /**
     * Halaman Pengaturan Sistem (identitas & layanan perpustakaan).
     */
    public function index()
    {
        $this->ensureAdmin();

        $nilai = Pengaturan::semua();

        return view('admin.pengaturan.index', compact('nilai'));
    }

    public function update(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'nama_perpustakaan'  => ['required', 'string', 'max:150'],
            'alamat'             => ['nullable', 'string', 'max:255'],
            'no_telp'            => ['nullable', 'string', 'max:30'],
            'jam_operasional'    => ['nullable', 'string', 'max:100'],
            'kepala_perpustakaan'=> ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data as $kunci => $nilai) {
            Pengaturan::set($kunci, $nilai);
        }

        return redirect()->route('admin.pengaturan')
            ->with('success', 'Pengaturan sistem berhasil disimpan.');
    }

    /**
     * Halaman Ketentuan Denda (aturan yang dipakai saat menghitung denda).
     */
    public function denda()
    {
        $this->ensureAdmin();

        $nilai = Pengaturan::semua();

        return view('admin.denda.ketentuan', compact('nilai'));
    }

    public function dendaUpdate(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'denda_per_hari'   => ['required', 'integer', 'min:0'],
            'lama_pinjam_hari' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        foreach ($data as $kunci => $nilai) {
            Pengaturan::set($kunci, $nilai);
        }

        return redirect()->route('admin.ketentuan-denda')
            ->with('success', 'Ketentuan denda berhasil disimpan.');
    }
}

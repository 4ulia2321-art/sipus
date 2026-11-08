@extends('layouts.master')

@section('title', 'Tentang SIPUS')

@section('content')
    <div class="greeting">
        <h1>Tentang SIPUS</h1>
        <p>Sistem Informasi Perpustakaan Sekolah.</p>
    </div>

    <section class="panel" style="max-width: 760px;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-building"></i> SIPUS
        </h3>
        <p style="font-size: 14px; line-height: 1.8; color: var(--muted); margin-bottom: 14px;">
            SIPUS (Sistem Informasi Perpustakaan) merupakan aplikasi perpustakaan digital yang
            digunakan oleh <strong>SMP Negeri 01 Pasirjambu</strong> untuk mengelola koleksi buku,
            pengajuan peminjaman, pengembalian, serta riwayat peminjaman siswa.
        </p>
        <p style="font-size: 14px; line-height: 1.8; color: var(--muted);">
            Silakan ajukan peminjaman buku ke petugas perpustakaan melalui menu
            <a href="{{ route('pengajuan') }}" style="color: var(--brand); font-weight: 700;">Pengajuan peminjaman</a>,
            dan pantau statusnya dari menu
            <a href="{{ route('riwayat') }}" style="color: var(--brand); font-weight: 700;">Riwayat peminjaman</a>.
        </p>
    </section>
@endsection
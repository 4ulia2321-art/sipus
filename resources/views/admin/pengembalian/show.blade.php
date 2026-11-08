@extends('layouts.master')

@section('title', 'Detail Pengembalian')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-arrow-repeat"></i>
            <div>
                <h1>Detail Pengembalian</h1>
                <p>Informasi lengkap data pengembalian buku.</p>
            </div>
        </div>
        <a href="{{ route($area.'.pengembalian') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h3>{{ $pengembalian->judul }}</h3>
            <span class="status-badge status-{{ $pengembalian->terlambat ? 'terlambat' : 'dikembalikan' }}">
                {{ $pengembalian->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
            </span>
        </div>

        <dl class="detail-meta">
            <div>
                <dt><i class="bi bi-person"></i> Nama Anggota</dt>
                <dd>{{ $pengembalian->nama_siswa }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-hash"></i> NIS</dt>
                <dd>{{ $pengembalian->nis }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-mortarboard"></i> Kelas</dt>
                <dd>{{ $pengembalian->kelas }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-book"></i> Judul Buku</dt>
                <dd>{{ $pengembalian->judul }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-feather"></i> Pengarang</dt>
                <dd>{{ $pengembalian->pengarang ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-upc"></i> No Seri Buku</dt>
                <dd>{{ $pengembalian->no_seri_buku }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-plus"></i> Tanggal Pinjam</dt>
                <dd>{{ $pengembalian->tanggal_pinjam->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-event"></i> Jatuh Tempo</dt>
                <dd>{{ $pengembalian->tanggal_jatuh_tempo->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-check"></i> Tanggal Kembali</dt>
                <dd>{{ $pengembalian->tanggal_kembali->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-hourglass-split"></i> Lama Terlambat</dt>
                <dd>{{ $pengembalian->lama_terlambat > 0 ? $pengembalian->lama_terlambat . ' hari' : '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-cash-coin"></i> Denda</dt>
                <dd>{{ $pengembalian->denda > 0 ? 'Rp ' . number_format($pengembalian->denda, 0, ',', '.') : '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-tag"></i> Kategori</dt>
                <dd>{{ $pengembalian->kategori ?? '-' }}</dd>
            </div>
        </dl>

        <div class="loan-actions" style="margin-top: 18px;">
            <a href="{{ route($area.'.peminjaman.show', $pengembalian->id_peminjaman) }}" class="btn-outline">
                <i class="bi bi-file-earmark-text"></i> Lihat Peminjaman
            </a>
        </div>
    </section>
@endsection

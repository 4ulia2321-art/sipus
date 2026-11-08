@extends('layouts.master')

@section('title', 'Detail Peminjaman')
@section('role', 'pustakawan')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-file-earmark-text"></i>
            <div>
                <h1>Detail Peminjaman</h1>
                <p>Informasi lengkap data peminjaman.</p>
            </div>
        </div>
        <a href="{{ route('pustakawan.peminjaman') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h3>{{ $peminjaman->judul }}</h3>
            <span class="status-badge status-{{ strtolower($peminjaman->status_efektif) }}">
                {{ $peminjaman->status_efektif }}
            </span>
        </div>

        <dl class="detail-meta">
            <div>
                <dt><i class="bi bi-person"></i> Nama Anggota</dt>
                <dd>{{ $peminjaman->nama_siswa }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-hash"></i> NIS</dt>
                <dd>{{ $peminjaman->nis }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-mortarboard"></i> Kelas</dt>
                <dd>{{ $peminjaman->kelas }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-book"></i> Judul Buku</dt>
                <dd>{{ $peminjaman->judul }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-feather"></i> Pengarang</dt>
                <dd>{{ $peminjaman->pengarang ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-upc"></i> No Seri Buku</dt>
                <dd>{{ $peminjaman->no_seri_buku }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-plus"></i> Tanggal Pinjam</dt>
                <dd>{{ \Carbon\Carbon::parse($peminjaman->tanggal_pinjam)->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-event"></i> Jatuh Tempo</dt>
                <dd>{{ \Carbon\Carbon::parse($peminjaman->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-check"></i> Tanggal Kembali</dt>
                <dd>{{ $peminjaman->tanggal_kembali ? \Carbon\Carbon::parse($peminjaman->tanggal_kembali)->translatedFormat('d M Y') : '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-cash-coin"></i> Denda</dt>
                <dd>{{ $peminjaman->denda > 0 ? 'Rp ' . number_format($peminjaman->denda, 0, ',', '.') : '-' }}</dd>
            </div>
        </dl>

        <div class="loan-actions" style="margin-top: 18px;">
            <a href="{{ route('pustakawan.peminjaman.edit', $peminjaman->id_peminjaman) }}" class="btn-outline">
                <i class="bi bi-pencil"></i> Edit
            </a>
        </div>
    </section>
@endsection
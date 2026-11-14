@extends('layouts.master')

@section('title', 'Detail Peminjaman')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/riwayat.css') }}">
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Beranda</a>
        <i class="bi bi-chevron-right"></i>
        <a href="{{ route('riwayat') }}">Riwayat Peminjaman</a>
        <i class="bi bi-chevron-right"></i>
        <span>Detail</span>
    </nav>

    {{-- Judul halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-clock-history"></i>
            <div>
                <h1>Detail Peminjaman</h1>
                <p>Informasi lengkap peminjaman dan pengembalian buku.</p>
            </div>
        </div>
        <a href="{{ route('riwayat') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if ($peminjaman->status_key === 'menunggu-pengembalian')
        <div class="alert alert-info">
            Pengajuan pengembalian sudah dikirim pada
            {{ $peminjaman->pengajuan_kembali_at->translatedFormat('d M Y H:i') }}
            dan sedang menunggu diproses pustakawan.
            @if ($peminjaman->hari_terlambat > 0)
                <br>
                Sudah terlambat {{ $peminjaman->hari_terlambat }} hari.
                Estimasi denda: <strong>Rp {{ number_format($peminjaman->estimasi_denda, 0, ',', '.') }}</strong>.
            @endif
        </div>
    @elseif ($peminjaman->status_key === 'dikembalikan')
        <div class="alert alert-success">
            Buku sudah dikembalikan pada
            {{ $peminjaman->tanggal_kembali?->translatedFormat('d M Y') ?? '-' }}.
        </div>
    @elseif ($peminjaman->status_key === 'terlambat')
        <div class="alert alert-error">
            Peminjaman ini sudah melewati jatuh tempo {{ $peminjaman->hari_terlambat }} hari.
            Estimasi denda: <strong>Rp {{ number_format($peminjaman->estimasi_denda, 0, ',', '.') }}</strong>.
            Ajukan pengembalian agar dapat diproses pustakawan.
        </div>
    @elseif ($peminjaman->status_key === 'menunggu')
        <div class="alert alert-info">
            Pengajuan peminjaman masih menunggu persetujuan pustakawan.
        </div>
    @elseif ($peminjaman->status_key === 'ditolak')
        <div class="alert alert-error">
            Pengajuan peminjaman ini ditolak.
        </div>
    @endif

    @if ($peminjaman->perpanjangan_menunggu)
        <div class="alert alert-info">
            Pengajuan perpanjangan sudah dikirim pada
            {{ $peminjaman->perpanjangan_diminta_at?->translatedFormat('d M Y H:i') }}
            dan sedang menunggu verifikasi pustakawan.
        </div>
    @elseif ($peminjaman->perpanjangan_status === 'Disetujui' && $peminjaman->status_key === 'dipinjam')
        <div class="alert alert-success">
            Pengajuan perpanjangan disetujui. Jatuh tempo baru:
            {{ $peminjaman->tanggal_jatuh_tempo->translatedFormat('d M Y') }}.
        </div>
    @elseif ($peminjaman->perpanjangan_status === 'Ditolak' && $peminjaman->status_key === 'dipinjam')
        <div class="alert alert-error">
            Pengajuan perpanjangan ditolak oleh pustakawan.
        </div>
    @endif

    {{-- Informasi peminjaman --}}
    <section class="panel">
        <div class="panel-head">
            <h3><i class="bi bi-info-circle"></i> Informasi Peminjaman</h3>
            <span class="status-badge status-{{ $peminjaman->status_key }}">{{ $peminjaman->status_label }}</span>
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
                <dt><i class="bi bi-calendar-plus"></i> Tanggal Pinjam</dt>
                <dd>{{ $peminjaman->tanggal_pinjam->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-event"></i> Jatuh Tempo</dt>
                <dd>{{ $peminjaman->tanggal_jatuh_tempo->translatedFormat('d M Y') }}</dd>
            </div>
            @if ($peminjaman->pengajuan_kembali_at)
                <div>
                    <dt><i class="bi bi-send-check"></i> Pengajuan Kembali</dt>
                    <dd>{{ $peminjaman->pengajuan_kembali_at->translatedFormat('d M Y H:i') }}</dd>
                </div>
            @endif
            @if ($peminjaman->perpanjangan_diminta_at)
                <div>
                    <dt><i class="bi bi-clock-history"></i> Pengajuan Perpanjangan</dt>
                    <dd>
                        {{ $peminjaman->perpanjangan_diminta_at->translatedFormat('d M Y H:i') }}
                        @if ($peminjaman->perpanjangan_status)
                            &middot; {{ $peminjaman->perpanjangan_status }}
                        @endif
                    </dd>
                </div>
            @endif
            @if ($peminjaman->tanggal_kembali)
                <div>
                    <dt><i class="bi bi-calendar-check"></i> Tanggal Kembali</dt>
                    <dd>{{ $peminjaman->tanggal_kembali->translatedFormat('d M Y') }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-cash-coin"></i> Denda</dt>
                    <dd>{{ (float) $peminjaman->denda > 0 ? 'Rp ' . number_format((float) $peminjaman->denda, 0, ',', '.') : '-' }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-credit-card"></i> Status Bayar</dt>
                    <dd>{{ $peminjaman->status_bayar ?: 'Belum Lunas' }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-tag"></i> Keterangan</dt>
                    <dd>{{ $peminjaman->kategori_pengembalian ?? '-' }}</dd>
                </div>
            @endif
        </dl>
    </section>

    {{-- Buku yang dipinjam --}}
    <section class="panel" style="margin-top: 16px;">
        <div class="panel-head">
            <h3><i class="bi bi-book"></i> Buku yang Dipinjam</h3>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            @forelse ($buku as $item)
                <div class="history-book">
                    <div class="history-thumb">
                        @if ($item->cover)
                            <img src="{{ asset('storage/' . $item->cover) }}" alt="{{ $item->judul }}">
                        @else
                            <i class="bi bi-image"></i>
                        @endif
                    </div>
                    <div>
                        <strong>{{ $item->judul }}</strong>
                        <small>{{ $item->pengarang }}</small>
                        <small>No. Seri: {{ $item->no_seri_buku }} &middot; Jumlah: {{ $item->jumlah }}</small>
                    </div>
                </div>
            @empty
                <p class="empty">Data buku untuk peminjaman ini tidak ditemukan.</p>
            @endforelse
        </div>
    </section>

    {{-- Aksi --}}
    @if ($peminjaman->bisa_ajukan || $peminjaman->bisa_perpanjangan)
        <div class="loan-actions" style="margin-top: 18px;">
            <a href="{{ route('riwayat') }}" class="btn-outline">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            @if ($peminjaman->bisa_perpanjangan)
                <form action="{{ route('riwayat.perpanjangan', $peminjaman->id_peminjaman) }}" method="POST"
                      onsubmit="return confirm(@js('Ajukan perpanjangan untuk peminjaman ini?'))">
                    @csrf
                    <button type="submit" class="btn-outline">
                        <i class="bi bi-clock-history"></i> Ajukan Perpanjangan
                    </button>
                </form>
            @endif
            @if ($peminjaman->bisa_ajukan)
                <form action="{{ route('riwayat.pengembalian', $peminjaman->id_peminjaman) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-send"></i> Ajukan Pengembalian
                    </button>
                </form>
            @endif
        </div>
    @else
        <div class="loan-actions" style="margin-top: 18px;">
            <a href="{{ route('riwayat') }}" class="btn-outline">
                <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
            </a>
        </div>
    @endif
@endsection

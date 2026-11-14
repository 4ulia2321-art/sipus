@extends('layouts.master')

@section('title', 'Notifikasi')

@section('content')
    @php
        $ikon = [
            'pengajuan'   => 'bi-file-earmark-text',
            'peminjaman'  => 'bi-book',
            'jatuh_tempo' => 'bi-calendar3',
            'terlambat'   => 'bi-exclamation-triangle',
            'sistem'      => 'bi-info-circle',
        ];
    @endphp

    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Beranda</a>
        <i class="bi bi-chevron-right"></i>
        <span>Notifikasi</span>
    </nav>

    {{-- Judul + aksi --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-bell"></i>
            <div>
                <h1>Notifikasi</h1>
                <p>Berikut adalah pemberitahuan terbaru untuk Anda.</p>
            </div>
        </div>

        @if ($notifikasi->isNotEmpty())
            <form action="{{ route('notifikasi.baca') }}" method="POST">
                @csrf
                <button type="submit" class="btn-outline btn-mark-all">
                    <i class="bi bi-check-lg"></i> Tandai Semua Sudah Dibaca
                </button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Daftar notifikasi --}}
    <div class="notif-list">
        @forelse ($notifikasi as $item)
            <a href="{{ $item->url }}" class="notif-item {{ ($item->dibaca ?? false) ? '' : 'unread' }}">
                <div class="notif-icon"><i class="bi {{ $ikon[$item->jenis] ?? 'bi-bell' }}"></i></div>

                <div class="notif-body">
                    <strong>{{ $item->judul }}</strong>
                    <p>{{ $item->pesan }}</p>
                    @if ($item->keterangan_denda)
                        <span class="denda-chip">
                            <i class="bi bi-cash-coin"></i> {{ $item->keterangan_denda }}
                        </span>
                    @endif

                    <div class="notif-meta-mobile">
                        <span>{{ $item->waktu->translatedFormat('d M Y') }} &bull; {{ $item->waktu->format('H:i') }}</span>
                        <span class="read-badge">{{ ($item->dibaca ?? false) ? 'Sudah dibaca' : 'Belum dibaca' }}</span>
                    </div>
                </div>

                <div class="notif-time">
                    <i class="bi bi-clock"></i>
                    <span>{{ $item->waktu->translatedFormat('d M Y') }}<br>{{ $item->waktu->format('H:i') }}</span>
                </div>
                <span class="read-badge notif-status">{{ ($item->dibaca ?? false) ? 'Sudah dibaca' : 'Belum dibaca' }}</span>

                <span class="btn-detail-outline notif-detail"><i class="bi bi-eye"></i> Detail</span>
                <i class="bi bi-chevron-right notif-chevron"></i>
            </a>
        @empty
            <p class="empty">Belum ada notifikasi.</p>
        @endforelse
    </div>

@endsection

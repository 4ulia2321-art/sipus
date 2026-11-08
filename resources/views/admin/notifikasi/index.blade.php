@extends('layouts.master')

@section('title', 'Notifikasi')

@section('content')
    <div class="greeting">
        <h1>Notifikasi</h1>
        <p>Informasi seputar pengajuan dan peminjaman buku.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h3><i class="bi bi-bell"></i> Semua Notifikasi</h3>
            @if ($notifikasi->isNotEmpty())
                <form action="{{ route('notifikasi.baca') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-outline">
                        <i class="bi bi-check2-all"></i> Tandai sudah dibaca
                    </button>
                </form>
            @endif
        </div>

        <ul class="notif-list">
            @forelse ($notifikasi as $notif)
                <li>
                    <a href="{{ $notif->url }}" class="notif-item">
                        <span class="notif-icon icon-{{ $notif->jenis }}">
                            @switch($notif->jenis)
                                @case('pengajuan') <i class="bi bi-file-earmark-text"></i> @break
                                @case('jatuh_tempo') <i class="bi bi-clock"></i> @break
                                @case('terlambat') <i class="bi bi-exclamation-triangle"></i> @break
                                @default <i class="bi bi-bell"></i> @endswitch
                        </span>
                        <span class="notif-body">
                            <strong>{{ $notif->judul }}</strong>
                            <span>{{ $notif->pesan }}</span>
                            <small>{{ $notif->waktu->translatedFormat('d F Y H:i') }}</small>
                        </span>
                        <span class="notif-arrow"><i class="bi bi-chevron-right"></i></span>
                    </a>
                </li>
            @empty
                <li class="empty">Tidak ada notifikasi saat ini.</li>
            @endforelse
        </ul>
    </section>
@endsection
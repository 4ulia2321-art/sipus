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
            <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $peminjaman->status_efektif)) }}">
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
            @if ($peminjaman->perpanjangan_status)
                <div>
                    <dt><i class="bi bi-clock-history"></i> Perpanjangan</dt>
                    <dd>
                        {{ $peminjaman->perpanjangan_status }}
                        @if ($peminjaman->perpanjangan_diminta_at)
                            <small class="muted d-block">
                                Diajukan {{ \Carbon\Carbon::parse($peminjaman->perpanjangan_diminta_at)->translatedFormat('d M Y H:i') }}
                            </small>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        <div class="loan-actions" style="margin-top: 18px;">
            @if ($peminjaman->perpanjangan_status === 'Menunggu')
                <form action="{{ route('pustakawan.peminjaman.perpanjangan.setujui', $peminjaman->id_peminjaman) }}" method="POST"
                      onsubmit="return confirm(@js('Setujui perpanjangan peminjaman ' . $peminjaman->judul . ' oleh ' . $peminjaman->nama_siswa . '?'))">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-check-lg"></i> Setujui Perpanjangan
                    </button>
                </form>
                <form action="{{ route('pustakawan.peminjaman.perpanjangan.tolak', $peminjaman->id_peminjaman) }}" method="POST"
                      onsubmit="return confirm(@js('Tolak perpanjangan peminjaman ' . $peminjaman->judul . ' oleh ' . $peminjaman->nama_siswa . '?'))">
                    @csrf
                    <button type="submit" class="btn-outline">
                        <i class="bi bi-x-lg"></i> Tolak Perpanjangan
                    </button>
                </form>
            @elseif ($peminjaman->status_efektif === 'Dipinjam')
                <form action="{{ route('pustakawan.peminjaman.perpanjang', $peminjaman->id_peminjaman) }}" method="POST"
                      onsubmit="return confirm(@js('Perpanjang peminjaman ' . $peminjaman->judul . ' oleh ' . $peminjaman->nama_siswa . '?'))">
                    @csrf
                    <button type="submit" class="btn-outline">
                        <i class="bi bi-clock"></i> Perpanjang
                    </button>
                </form>
            @endif
            <a href="{{ route('pustakawan.peminjaman.edit', $peminjaman->id_peminjaman) }}" class="btn-outline">
                <i class="bi bi-pencil"></i> Edit
            </a>
        </div>
    </section>
@endsection
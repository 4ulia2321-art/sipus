@extends('layouts.master')

@section('title', $buku->judul)

@section('content')
    <a href="{{ route('katalog') }}" class="btn-back">
        <i class="bi bi-chevron-left"></i> Kembali ke katalog
    </a>

    <section class="book-detail">
        {{-- Cover buku --}}
        <div class="detail-cover">
            @if ($buku->cover)
                <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}">
            @else
                <i class="bi bi-image"></i>
            @endif
        </div>

        {{-- Info buku --}}
        <div class="detail-info">
            <h1>{{ $buku->judul }}</h1>
            <h2>{{ $buku->pengarang }}</h2>

            <dl class="detail-meta">
                <div>
                    <dt><i class="bi bi-person"></i> Pengarang</dt>
                    <dd>{{ $buku->pengarang }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-book"></i> Kategori</dt>
                    <dd>{{ $buku->kategori }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-calendar3"></i> Tahun Terbit</dt>
                    <dd>{{ $buku->tahun_terbit }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-layers-fill"></i> Jumlah Halaman</dt>
                    <dd>{{ $buku->jumlah_halaman }} Halaman</dd>
                </div>
                <div>
                    <dt><i class="bi bi-geo-alt-fill"></i> Lokasi Rak</dt>
                    <dd>{{ $buku->lokasi_rak }}</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- Sinopsis + tombol aksi --}}
    <section class="detail-bottom">
        <div class="synopsis">
            <h3><i class="bi bi-book"></i> Sinopsis</h3>
            <div class="synopsis-box">
                {{ $buku->sinopsis }}
            </div>
        </div>

        @if ($peminjamanAktif)
            @if ($peminjamanAktif->pengajuan_kembali_at)
                <div class="alert alert-info">
                    <i class="bi bi-hourglass-split"></i>
                    Pengajuan pengembalian buku ini sedang menunggu diproses pustakawan.
                </div>
            @else
                <form action="{{ route('riwayat.pengembalian', $peminjamanAktif->id_peminjaman) }}" method="POST" class="detail-action">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-send"></i> Ajukan pengembalian
                    </button>
                </form>
            @endif
        @endif
    </section>
@endsection

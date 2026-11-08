@extends('layouts.master')

@section('title', 'Pengajuan Peminjaman')

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Beranda</a>
        <i class="bi bi-chevron-right"></i>
        <span>Pengajuan Peminjaman</span>
    </nav>

    {{-- Judul halaman --}}
    <div class="page-title">
        <i class="bi bi-file-earmark-plus"></i>
        <div>
            <h1>Pengajuan Peminjaman</h1>
            <p>Lengkapi data peminjaman buku berikut.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('pengajuan.store') }}" method="POST" class="loan-form">
        @csrf
        <input type="hidden" name="buku_id" value="{{ $buku->id }}">

        <div class="loan-grid">
            {{-- Kolom kiri: informasi buku + informasi --}}
            <div class="loan-left">
                <div class="card">
                    <h3 class="card-title">Informasi Buku</h3>
                    <div class="loan-book">
                        <div class="loan-cover">
                            @if ($buku->cover)
                                <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}">
                            @else
                                <i class="bi bi-image"></i>
                            @endif
                        </div>
                        <div class="loan-book-info">
                            <h2>{{ $buku->judul }}</h2>
                            <p class="author">{{ $buku->pengarang }}</p>
                            <dl class="detail-meta compact">
                                <div><dt><i class="bi bi-book"></i> Kategori</dt><dd>{{ $buku->kategori }}</dd></div>
                                <div><dt><i class="bi bi-database"></i> Tahun Terbit</dt><dd>{{ $buku->tahun_terbit }}</dd></div>
                                <div><dt><i class="bi bi-layers-fill"></i> Jumlah Halaman</dt><dd>{{ $buku->jumlah_halaman }}</dd></div>
                                <div><dt><i class="bi bi-geo-alt-fill"></i> Lokasi Rak</dt><dd>{{ $buku->lokasi_rak }}</dd></div>
                            </dl>
                            <span class="status-pill {{ $buku->status === 'tersedia' ? '' : 'status-out' }}">
                                <i class="bi bi-check-circle"></i> {{ ucfirst($buku->status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card info-box">
                    <h4><i class="bi bi-info-circle"></i> Informasi</h4>
                    <ul>
                        <li>Pastikan buku yang dipilih tersedia di perpustakaan.</li>
                        <li>Lama peminjaman adalah {{ $lamaPinjam }} hari (dapat diperpanjang).</li>
                        <li>Keterlambatan pengembalian akan dikenakan denda.</li>
                    </ul>
                </div>
            </div>

            {{-- Kolom kanan: data peminjaman --}}
            <div class="loan-right">
                <div class="card">
                    <h3 class="card-title">Data Peminjaman</h3>

                    <div class="form-group">
                        <label for="nis"><i class="bi bi-person"></i> NIS</label>
                        <input type="text" id="nis" name="nis" value="{{ old('nis', auth()->user()->nis ?? '') }}"
                               placeholder="Masukkan NIS" inputmode="numeric" required
                               class="@error('nis') is-invalid @enderror">
                        @error('nis') <small class="field-error">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <label for="tanggal_pinjam"><i class="bi bi-calendar3"></i> Tanggal Peminjaman</label>
                        <input type="text" id="tanggal_pinjam" value="{{ $tanggalPinjam->translatedFormat('d F Y') }}" readonly>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_kembali">
                            <i class="bi bi-calendar3"></i> Tanggal Pengembalian <small>(Jatuh Tempo)</small>
                        </label>
                        <input type="text" id="tanggal_kembali" value="{{ $tanggalKembali->translatedFormat('d F Y') }}" readonly>
                    </div>
                </div>

                <div class="loan-actions">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('katalog') }}" class="btn-outline">
                        <i class="bi bi-arrow-left"></i> Batal / Kembali
                    </a>
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-send"></i> Ajukan Peminjaman
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

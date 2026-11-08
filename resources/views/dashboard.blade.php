@extends('layouts.master')

@section('title', 'Dashboard')

@section('content')
    {{-- Sapaan --}}
    <section class="hero">
        <div class="hero-art"><i class="bi bi-journal-bookmark-fill"></i></div>
        <div>
            <h1>Halo, {{ $nama }}!</h1>
            <p>Selamat datang di SIPUS, semoga harimu menyenangkan!</p>
            <div class="hero-bar"></div>
        </div>
    </section>

    {{-- Kartu statistik --}}
    <section class="stat-grid" data-live-part="stats" data-autorefresh="30000">
        <div class="stat-card stat-blue">
            <div class="stat-icon"><i class="bi bi-book"></i></div>
            <div>
                <strong>Total Buku</strong>
                <span>{{ number_format($totalBuku, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon"><i class="bi bi-journal-check"></i></div>
            <div>
                <strong>Buku Tersedia</strong>
                <span>{{ number_format($bukuTersedia, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon"><i class="bi bi-person-raised-hand"></i></div>
            <div>
                <strong>Buku Dipinjam</strong>
                <span>{{ number_format($bukuDipinjam, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <strong>Pinjaman Saya</strong>
                <span>{{ number_format($bukuDipinjamSaya, 0, ',', '.') }}</span>
            </div>
        </div>
    </section>

    {{-- Buku tersedia + Pengumuman --}}
    <section class="panel-grid">
        <div class="panel">
            <div class="panel-head">
                <h3><i class="bi bi-book"></i> Buku Tersedia</h3>
                <a href="{{ route('katalog') }}" class="see-all">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="book-list">
                @forelse ($bukuList as $buku)
                    <div class="book-card">
                        <div class="book-cover cover-{{ ($loop->index % 3) + 1 }}">
                            @if ($buku->cover)
                                <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}">
                            @else
                                <i class="bi bi-book"></i>
                            @endif
                        </div>
                        <h4>{{ $buku->judul }}</h4>
                        <small>{{ $buku->pengarang }}</small>
                        <a href="{{ route('buku.show', $buku->id) }}" class="btn-book">
                            <i class="bi bi-book"></i> Lihat Detail
                        </a>
                    </div>
                @empty
                    <p class="empty">Belum ada buku tersedia.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h3><i class="bi bi-megaphone"></i> Pengumuman</h3>
                <a href="#" class="see-all">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <ul class="announce-list">
                @forelse ($pengumuman as $item)
                    <li>
                        <div class="announce-img ann-{{ ($loop->index % 3) + 1 }}">
                            @if ($item->gambar)
                                <img src="{{ asset('storage/' . $item->gambar) }}" alt="{{ $item->judul }}">
                            @else
                                <i class="bi {{ $item->ikon ?? 'bi-megaphone' }}"></i>
                            @endif
                        </div>
                        <div class="announce-body">
                            <strong>{{ $item->judul }}</strong>
                            <p>{{ $item->isi }}</p>
                            <small>
                                <i class="bi bi-calendar3"></i>
                                {{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d F Y') }}
                            </small>
                        </div>
                        <i class="bi bi-chevron-right announce-chevron"></i>
                    </li>
                @empty
                    <li class="empty">Belum ada pengumuman.</li>
                @endforelse
            </ul>
        </div>
    </section>

    {{-- Riwayat peminjaman terakhir --}}
    <section class="panel">
        <div class="panel-head">
            <h3><i class="bi bi-clock"></i> Riwayat Peminjaman Terakhir</h3>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Pengarang</th>
                    <th>Tanggal Pinjam</th>
                    <th>Tanggal Kembali</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riwayat as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row->pengarang }}</td>
                        <td>{{ $row->tanggal_pinjam ? \Carbon\Carbon::parse($row->tanggal_pinjam)->translatedFormat('d F Y') : '-' }}</td>
                        <td>{{ $row->tanggal_kembali ? \Carbon\Carbon::parse($row->tanggal_kembali)->translatedFormat('d F Y') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada riwayat peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection

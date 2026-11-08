@extends('layouts.master')

@section('title', 'Dashboard Admin')
@section('role', 'admin')

@section('content')
    {{-- Kartu profil (mobile) --}}
    <a href="{{ route('profil') }}" class="profile-card mobile-only">
        <i class="bi bi-person-circle avatar"></i>
        <div>
            <strong>{{ $nama }}</strong>
            <span>Admin</span>
        </div>
        <i class="bi bi-chevron-right"></i>
    </a>

    {{-- Sapaan + tanggal (desktop) --}}
    <section class="admin-hero desktop-only">
        <div>
            <small class="crumb">Dashboard</small>
            <div class="admin-hero-title">
                <i class="bi bi-book"></i>
                <div>
                    <h1>Selamat Datang, {{ $nama }}</h1>
                    <p>Kelola perpustakaan dengan mudah dan efisien melalui SIPUS.</p>
                </div>
            </div>
        </div>

        <div class="date-box">
            <i class="bi bi-calendar3"></i>
            <div>
                <strong>{{ $sekarang->translatedFormat('l, d F Y') }}</strong>
                <span>{{ $sekarang->format('H:i') }} WIB</span>
            </div>
        </div>
    </section>

    {{-- Kartu statistik --}}
    <section class="stat-grid" data-live-part="stats" data-autorefresh="30000">
        @foreach ($stat as $s)
            <div class="stat-card stat-plain">
                <div class="stat-icon-soft"><i class="bi {{ $s['ikon'] }}"></i></div>
                <div>
                    <strong>{{ $s['label'] }}</strong>
                    <span>{{ number_format($s['nilai'], 0, ',', '.') }}</span>
                    <small>{{ $s['ket'] }}</small>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Peminjaman & pengembalian terbaru --}}
    <section class="admin-grid">
        <div class="panel panel-admin">
            <div class="panel-head">
                <h3>Peminjaman Terbaru</h3>
                <a href="{{ route('admin.laporan', ['tab' => 'peminjaman']) }}" class="see-all">Lihat Semua</a>
            </div>

            {{-- Tabel (desktop) --}}
            <div class="table-scroll admin-table-desktop">
                <table class="table table-compact">
                    <thead>
                        <tr><th>No</th><th>Nama Anggota</th><th>Judul Buku</th><th>Tanggal Pinjam</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($peminjaman as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><div class="person">@include('partials.avatar', ['foto' => $row->foto]) {{ $row->nama }}</div></td>
                                <td>{{ $row->judul }}</td>
                                <td>{{ $row->tanggal->translatedFormat('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada peminjaman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Daftar (mobile) --}}
            <ul class="recent-list">
                @foreach ($peminjaman as $i => $row)
                    <li>
                        <a href="{{ route('admin.laporan', ['tab' => 'peminjaman']) }}">
                            <span class="recent-no">{{ $i + 1 }}</span>
                            @include('partials.avatar', ['foto' => $row->foto])
                            <div class="recent-info">
                                <strong>{{ $row->nama }}</strong>
                                <span>{{ $row->judul }}</span>
                                <span>{{ $row->tanggal->translatedFormat('d M Y') }}</span>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="panel panel-admin">
            <div class="panel-head">
                <h3>Pengembalian Terbaru</h3>
                <a href="{{ route('admin.laporan', ['tab' => 'pengembalian']) }}" class="see-all">Lihat Semua</a>
            </div>
            <div class="table-scroll">
                <table class="table table-compact">
                    <thead>
                        <tr><th>No</th><th>Nama Anggota</th><th>Judul Buku</th><th>Tanggal Kembali</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pengembalian as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><div class="person">@include('partials.avatar', ['foto' => $row->foto]) {{ $row->nama }}</div></td>
                                <td>{{ $row->judul }}</td>
                                <td>{{ $row->tanggal->translatedFormat('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada pengembalian.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Daftar keterlambatan --}}
    <section class="panel panel-admin">
        <div class="panel-head">
            <h3>Daftar Keterlambatan</h3>
            <a href="{{ route('admin.laporan', ['tab' => 'keterlambatan']) }}" class="see-all">Lihat Semua</a>
        </div>
        <div class="table-scroll">
            <table class="table table-compact table-late">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Anggota</th>
                        <th>Judul Buku</th>
                        <th>Tanggal Jatuh Tempo</th>
                        <th>Tanggal Dikembalikan</th>
                        <th>Lama Terlambat</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($keterlambatan as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->nama }}</td>
                            <td>{{ $row->judul }}</td>
                            <td>{{ $row->jatuh_tempo->translatedFormat('d M Y') }}</td>
                            <td>{{ $row->dikembalikan?->translatedFormat('d M Y') ?? '-' }}</td>
                            <td>{{ $row->lama ?? '-' }}</td>
                            <td>
                                <span class="pill {{ $row->status === 'terlambat' ? 'pill-late' : 'pill-borrowed' }}">
                                    {{ ucfirst($row->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">Tidak ada keterlambatan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

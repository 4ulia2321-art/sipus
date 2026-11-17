@extends('layouts.master')

@section('title', 'Detail Buku')
@section('role', 'pustakawan')
@section('search_placeholder', 'Cari judul, pengarang, atau nomor seri buku...')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
@endpush

@section('content')
    @php
        $stok = (int) $buku->stok;
        $status = $stok <= 0 ? 'Habis' : ($stok <= 2 ? 'Hampir Habis' : 'Tersedia');
        $statusClass = ['Tersedia' => 'green', 'Hampir Habis' => 'yellow', 'Habis' => 'red'];
        $cover = [
            ['#2563eb', '#60a5fa'],
            ['#16a34a', '#4ade80'],
            ['#d97706', '#fbbf24'],
            ['#7c3aed', '#a78bfa'],
            ['#ef4444', '#f87171'],
        ][crc32((string) ($buku->nama_kategori ?? $buku->kategori)) % 5];
    @endphp

    {{-- Header halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-book-half"></i>
            <div>
                <h2>Detail Buku</h2>
                <p>Informasi lengkap koleksi buku perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route('pustakawan.buku') }}" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
    @endif

    <section class="book-detail">
        {{-- Sampul buku --}}
        <div class="detail-cover" style="background: linear-gradient(160deg, {{ $cover[0] }}, {{ $cover[1] }});">
            @if (! empty($buku->cover))
                <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}">
            @else
                <span style="padding: 20px; color: #fff; font-size: 20px; font-weight: 800; text-align: center; line-height: 1.4;">
                    {{ $buku->judul }}
                </span>
            @endif
        </div>

        {{-- Informasi buku --}}
        <div class="detail-info">
            <h1>{{ $buku->judul }}</h1>
            <h2>{{ $buku->pengarang ?: 'Pengarang belum diisi' }}</h2>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px;">
                <span class="pill {{ $statusClass[$status] ?? 'green' }}">{{ $status }}</span>
                <span class="pill blue">{{ $buku->nama_kategori ?? ($buku->kategori ?: 'Tanpa Kategori') }}</span>
            </div>

            <dl class="detail-meta">
                <div>
                    <dt><i class="bi bi-upc-scan"></i> No Seri Buku</dt>
                    <dd>{{ $buku->no_seri_buku }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-bookmark"></i> Kategori</dt>
                    <dd>{{ $buku->nama_kategori ?? ($buku->kategori ?: '-') }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-person"></i> Pengarang</dt>
                    <dd>{{ $buku->pengarang ?: '-' }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-building"></i> Penerbit</dt>
                    <dd>{{ $buku->penerbit ?: '-' }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-calendar3"></i> Tahun Terbit</dt>
                    <dd>{{ $buku->tahun_terbit ?: '-' }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-geo-alt-fill"></i> Lokasi Rak</dt>
                    <dd>{{ $buku->lokasi_rak ?: '-' }}</dd>
                </div>
                <div>
                    <dt><i class="bi bi-box-seam"></i> Stok Tersedia</dt>
                    <dd>{{ $stok }} salinan</dd>
                </div>
                <div>
                    <dt><i class="bi bi-arrow-left-right"></i> Sedang Dipinjam</dt>
                    <dd>{{ $sedangDipinjam }} salinan</dd>
                </div>
                <div>
                    <dt><i class="bi bi-clock-history"></i> Total Peminjaman</dt>
                    <dd>{{ $totalPinjam }} transaksi</dd>
                </div>
            </dl>

            <div class="loan-actions" style="margin-top: 18px;">
                <a href="{{ route('pustakawan.buku.edit', $buku->id_buku) }}" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Edit Buku
                </a>

                @if ($bisaHapus)
                    <form action="{{ route('pustakawan.buku.destroy', $buku->id_buku) }}" method="POST"
                          onsubmit="return confirm('Hapus buku &quot;{{ $buku->judul }}&quot;?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline" style="color: #dc2626; border-color: #fecaca;">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </form>
                @else
                    <span class="muted" style="font-size: 12.5px;">
                        <i class="bi bi-lock"></i> Buku tidak dapat dihapus karena memiliki riwayat peminjaman.
                    </span>
                @endif
            </div>
        </div>
    </section>

    {{-- Riwayat peminjaman buku --}}
    <div class="card table-card" style="margin-top: 18px;">
        <div class="panel-head" style="padding: 16px 18px; margin: 0;">
            <h3><i class="bi bi-clock-history"></i> Riwayat Peminjaman Buku</h3>
        </div>
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Anggota</th>
                        <th>Kelas</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Tanggal Kembali</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $i => $row)
                        @php
                            $jatuhTempo = \Carbon\Carbon::parse($row->tanggal_jatuh_tempo);
                            if ($row->tanggal_kembali) {
                                $statusRiwayat = 'Dikembalikan';
                            } elseif ($row->status === 'Dipinjam' && $jatuhTempo->lt(\Carbon\Carbon::today())) {
                                $statusRiwayat = 'Terlambat';
                            } else {
                                $statusRiwayat = $row->status;
                            }
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->nama_siswa }}</td>
                            <td>{{ $row->kelas }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->tanggal_pinjam)->translatedFormat('d M Y') }}</td>
                            <td>{{ $jatuhTempo->translatedFormat('d M Y') }}</td>
                            <td>{{ $row->tanggal_kembali ? \Carbon\Carbon::parse($row->tanggal_kembali)->translatedFormat('d M Y') : '-' }}</td>
                            <td>
                                <span class="status-badge status-{{ strtolower($statusRiwayat) }}">{{ $statusRiwayat }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">Belum ada riwayat peminjaman untuk buku ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

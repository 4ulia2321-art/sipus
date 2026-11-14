@extends('layouts.master')

@section('title', 'Riwayat Peminjaman')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/riwayat.css') }}">
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Beranda</a>
        <i class="bi bi-chevron-right"></i>
        <span>Riwayat Peminjaman</span>
    </nav>

    {{-- Judul halaman --}}
    <div class="page-title">
        <i class="bi bi-clock-history"></i>
        <div>
            <h1>Riwayat Peminjaman</h1>
            <p>Daftar buku yang pernah Anda pinjam di perpustakaan.</p>
        </div>
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

    {{-- Pencarian & filter --}}
    <form action="{{ route('riwayat') }}" method="GET" class="history-filter" data-live-form>
        <div class="history-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari judul buku...">
        </div>

        <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['dipinjam' => 'Dipinjam', 'terlambat' => 'Terlambat', 'menunggu-pengembalian' => 'Menunggu Pengembalian', 'menunggu-pembayaran' => 'Menunggu Pembayaran', 'dikembalikan' => 'Dikembalikan'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="tahun" onchange="this.form.submit()">
            <option value="">Semua Tahun</option>
            @foreach ($tahunList as $tahun)
                <option value="{{ $tahun }}" @selected((string) request('tahun') === (string) $tahun)>{{ $tahun }}</option>
            @endforeach
        </select>

        <a href="{{ route('riwayat') }}" class="btn-outline btn-reset">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
        </a>
    </form>

    {{-- Tabel (desktop) --}}
    <div class="history-table-wrap" data-live-part="list">
        <table class="history-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>No. Buku</th>
                    <th class="col-book">Buku</th>
                    <th>Tanggal Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Tanggal Dikembalikan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riwayat as $i => $row)
                    <tr>
                        <td>{{ $riwayat->firstItem() + $i }}</td>
                        <td>{{ $row->buku_id }}</td>
                        <td class="col-book">
                            <div class="history-book">
                                <div class="history-thumb">
                                    @if ($row->cover)
                                        <img src="{{ asset('storage/' . $row->cover) }}" alt="{{ $row->judul }}">
                                    @else
                                        <i class="bi bi-image"></i>
                                    @endif
                                </div>
                                <div>
                            <strong>{{ $row->judul }}</strong>
                            <small>{{ $row->pengarang }}</small>
                            <small>Kategori: {{ $row->kategori }}</small>
                        </div>
                    </div>
                </td>
                <td>{{ $row->tanggal_pinjam->translatedFormat('d M Y') }}</td>
                <td>{{ $row->jatuh_tempo->translatedFormat('d M Y') }}</td>
                <td>{{ $row->tanggal_kembali?->translatedFormat('d M Y') ?? '-' }}</td>
                <td>
                    <div class="status-stack">
                        <span class="status-badge status-{{ $row->status }}">{{ $row->status_label }}</span>
                        @if ($row->perpanjangan_status === 'Menunggu')
                            <span class="status-badge status-menunggu">Perpanjangan Menunggu</span>
                        @elseif ($row->perpanjangan_status === 'Disetujui' && $row->status === 'dipinjam')
                            <span class="status-badge status-dikembalikan">Diperpanjang</span>
                        @elseif ($row->perpanjangan_status === 'Ditolak' && $row->status === 'dipinjam')
                            <span class="status-badge status-ditolak">Perpanjangan Ditolak</span>
                        @endif
                    </div>
                </td>
                <td>
                    <a href="{{ route('riwayat.show', $row->id_peminjaman) }}" class="btn-detail-outline">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Belum ada riwayat peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($riwayat->hasPages())
            <div class="history-pagination">
                {{ $riwayat->links('partials.pagination') }}
            </div>
        @endif
    </div>

    {{-- Kartu (mobile) --}}
    <div class="history-cards" data-live-part="list">
        @foreach ($riwayat as $row)
            <article class="history-card">
                <div class="history-card-top">
                    <div class="history-thumb">
                        @if ($row->cover)
                            <img src="{{ asset('storage/' . $row->cover) }}" alt="{{ $row->judul }}">
                        @else
                            <i class="bi bi-image"></i>
                        @endif
                    </div>
                    <div>
                        <strong>{{ $row->judul }}</strong>
                        <small>{{ $row->pengarang }}</small>
                        <small>{{ $row->kategori }}</small>
                    </div>
                </div>
                <dl class="history-dates">
                    <div><dt><i class="bi bi-calendar3"></i> Pinjam</dt><dd>{{ $row->tanggal_pinjam->translatedFormat('d M Y') }}</dd></div>
                    <div><dt><i class="bi bi-calendar3"></i> Jatuh Tempo</dt><dd>{{ $row->jatuh_tempo->translatedFormat('d M Y') }}</dd></div>
                    <div><dt><i class="bi bi-calendar3"></i> Kembali</dt><dd>{{ $row->tanggal_kembali?->translatedFormat('d M Y') ?? '-' }}</dd></div>
                </dl>
                <div class="history-card-bottom">
                    <div class="status-stack">
                        <span class="status-badge status-{{ $row->status }}">{{ $row->status_label }}</span>
                        @if ($row->perpanjangan_status === 'Menunggu')
                            <span class="status-badge status-menunggu">Perpanjangan Menunggu</span>
                        @elseif ($row->perpanjangan_status === 'Disetujui' && $row->status === 'dipinjam')
                            <span class="status-badge status-dikembalikan">Diperpanjang</span>
                        @elseif ($row->perpanjangan_status === 'Ditolak' && $row->status === 'dipinjam')
                            <span class="status-badge status-ditolak">Perpanjangan Ditolak</span>
                        @endif
                    </div>
                    <a href="{{ route('riwayat.show', $row->id_peminjaman) }}" class="btn-detail-outline">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                </div>
            </article>
        @endforeach

        @if ($riwayat->hasPages())
            {{ $riwayat->links('partials.pagination') }}
        @endif
    </div>
@endsection

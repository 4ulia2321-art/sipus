@extends('layouts.master')

@section('title', 'Rekap Data')
@section('role', 'admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/laporan.css') }}">
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <span>Rekap Data</span>
    </nav>

    {{-- Navigasi gabungan Rekap Data, Statistik, dan Laporan Keseluruhan --}}
    @include('partials.rekap-tabs')

    {{-- Header halaman --}}
    <div class="page-header">
        <div class="page-title">
            <i class="bi bi-table"></i>
            <div>
                <h1>Rekap Data</h1>
                <p>Ringkasan peminjaman berdasarkan kelas, kategori buku, dan status.</p>
            </div>
        </div>

        <span class="badge badge-pill badge-info">
            <i class="bi bi-calendar3"></i>&nbsp; {{ $periodeLabel }}
        </span>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.rekap') }}" class="card report-card rekap-filter-card">
        <div class="rekap-filter">
            <div class="form-group">
                <label for="filter-kelas">Kelas</label>
                <select name="kelas" id="filter-kelas">
                    <option value="">Semua kelas</option>
                    @foreach ($opsiKelas as $kelas)
                        <option value="{{ $kelas->id_kelas }}" @selected((int) $filter['kelas'] === (int) $kelas->id_kelas)>
                            {{ $kelas->nama_kelas }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="filter-kategori">Kategori Buku</label>
                <select name="kategori" id="filter-kategori">
                    <option value="">Semua kategori</option>
                    @foreach ($opsiKategori as $kategori)
                        <option value="{{ $kategori->id_kategori }}" @selected((int) $filter['kategori'] === (int) $kategori->id_kategori)>
                            {{ $kategori->nama_kategori }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="filter-status">Status</label>
                <select name="status" id="filter-status">
                    <option value="">Semua status</option>
                    @foreach ($opsiStatus as $status)
                        <option value="{{ $status }}" @selected($filter['status'] === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="filter-mulai">Tanggal Mulai</label>
                <input type="date" name="mulai" id="filter-mulai" value="{{ $filter['mulai'] }}">
            </div>

            <div class="form-group">
                <label for="filter-selesai">Tanggal Selesai</label>
                <input type="date" name="selesai" id="filter-selesai" value="{{ $filter['selesai'] }}">
            </div>

            <div class="rekap-filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="bi bi-funnel"></i> Terapkan
                </button>
                <a href="{{ route('admin.rekap') }}" class="btn-outline">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </div>
    </form>

    {{-- Kartu ringkasan --}}
    @php
        $cards = [
            [
                'icon'  => 'bi-journal-arrow-up',
                'label' => 'Total Peminjaman',
                'value' => $ringkasan['total_transaksi'],
                'sub'   => 'Transaksi peminjaman',
            ],
            [
                'icon'  => 'bi-collection',
                'label' => 'Total Buku',
                'value' => $ringkasan['total_buku'],
                'sub'   => 'Rata-rata ' . number_format($ringkasan['rata_buku'], 1, ',', '.') . ' salinan per transaksi',
            ],
            [
                'icon'  => 'bi-diagram-3',
                'label' => 'Kelas Terlibat',
                'value' => $ringkasan['total_kelas'],
                'sub'   => 'Kelas dengan peminjaman',
            ],
            [
                'icon'  => 'bi-tags',
                'label' => 'Kategori Terlibat',
                'value' => $ringkasan['total_kategori'],
                'sub'   => 'Kategori buku yang dipinjam',
            ],
        ];
    @endphp

    <div class="r-stats">
        @foreach ($cards as $card)
            <div class="card r-stat">
                <div class="r-stat-top">
                    <div class="r-stat-icon"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div>
                        <div class="r-stat-label">{{ $card['label'] }}</div>
                        <div class="r-stat-value">{{ number_format($card['value'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="r-stat-sub">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </div>

    <p class="rekap-hint">
        <i class="bi bi-info-circle"></i>
        {{ $adaFilter ? 'Menampilkan data yang sesuai filter.' : 'Menampilkan seluruh data peminjaman.' }}
        Filter berlaku untuk kartu ringkasan dan seluruh tabel di bawah.
        Satu transaksi dapat memuat beberapa kategori, sehingga jumlah transaksi
        per kategori tidak dijumlahkan sebagai total keseluruhan.
    </p>

    {{-- Tabel per kelas dan per kategori --}}
    <div class="rekap-grid">
        <div class="card report-card">
            <div class="rekap-head">
                <h2 class="rekap-title"><i class="bi bi-diagram-3"></i> Rekap per Kelas</h2>
                <span class="rekap-periode">{{ $ringkasan['total_transaksi'] }} transaksi</span>
            </div>

            <div class="table-wrap">
                <table class="table report-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kelas</th>
                            <th class="rekap-num">Transaksi</th>
                            <th class="rekap-num">Jumlah Buku</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($perKelas as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['label'] }}</td>
                                <td class="rekap-num">{{ number_format($row['total_transaksi'], 0, ',', '.') }}</td>
                                <td class="rekap-num">{{ number_format($row['total_buku'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty">Tidak ada data untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card report-card">
            <div class="rekap-head">
                <h2 class="rekap-title"><i class="bi bi-tags"></i> Rekap per Kategori</h2>
                <span class="rekap-periode">{{ $ringkasan['total_buku'] }} buku</span>
            </div>

            <div class="table-wrap">
                <table class="table report-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kategori Buku</th>
                            <th class="rekap-num">Transaksi</th>
                            <th class="rekap-num">Jumlah Buku</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($perKategori as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['label'] }}</td>
                                <td class="rekap-num">{{ number_format($row['total_transaksi'], 0, ',', '.') }}</td>
                                <td class="rekap-num">{{ number_format($row['total_buku'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty">Tidak ada data untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tabel per status --}}
    <div class="card report-card">
        <div class="rekap-head">
            <h2 class="rekap-title"><i class="bi bi-check2-circle"></i> Rekap per Status</h2>
            <span class="rekap-periode">{{ $periodeLabel }}</span>
        </div>

        <div class="table-wrap">
            <table class="table report-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Status Peminjaman</th>
                        <th class="rekap-num">Transaksi</th>
                        <th class="rekap-num">Jumlah Buku</th>
                        <th class="rekap-num">Porsi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($perStatus as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <span class="badge badge-pill {{ $badgeStatus[$row['label']] ?? 'badge-secondary' }}">
                                    {{ $row['label'] }}
                                </span>
                            </td>
                            <td class="rekap-num">{{ number_format($row['total_transaksi'], 0, ',', '.') }}</td>
                            <td class="rekap-num">{{ number_format($row['total_buku'], 0, ',', '.') }}</td>
                            <td class="rekap-num">{{ number_format($row['persen'], 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">Tidak ada data untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('partials.rekap-judul')
@endsection

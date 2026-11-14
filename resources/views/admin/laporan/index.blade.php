@extends('layouts.master')

@section('title', 'Laporan')
@section('role', 'admin')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/laporan.css') }}">
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route($area.'.dashboard') }}">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <span>Laporan</span>
    </nav>

    {{-- Form filter (rentang tanggal + opsi grafik + tab) --}}
    <form method="GET" action="{{ route($area.'.laporan') }}" id="filter-form">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="hidden" name="mulai" id="mulai" value="{{ $mulai->format('Y-m-d') }}">
        <input type="hidden" name="selesai" id="selesai" value="{{ $selesai->format('Y-m-d') }}">

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-clipboard2-check"></i>
                <div>
                    <h1>Laporan</h1>
                    <p>Lihat dan unduh laporan data perpustakaan.</p>
                </div>
            </div>

            <div class="field field-date range-field">
                <i class="bi bi-calendar3"></i>
                <input type="text" id="rentang" placeholder="Pilih rentang tanggal" readonly>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>
    </form>

    {{-- Statistik --}}
    <div class="r-stats">
        @php
            $cards = [
                ['icon' => 'bi-book',            'label' => 'Total Buku',    'value' => $stat['buku'],         'sub' => number_format($stat['tersedia'], 0, ',', '.') . ' tersedia · ' . number_format($stat['dipinjam'], 0, ',', '.') . ' dipinjam'],
                ['icon' => 'bi-people-fill',     'label' => 'Total Anggota', 'value' => $stat['anggota'],      'sub' => 'Data seluruh anggota'],
                ['icon' => 'bi-journal-text',    'label' => 'Peminjaman',    'value' => $stat['peminjaman'],   'sub' => 'Dalam periode'],
                ['icon' => 'bi-arrow-repeat',    'label' => 'Pengembalian',  'value' => $stat['pengembalian'], 'sub' => 'Dalam periode'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="card r-stat">
                <div class="r-stat-top">
                    <div class="r-stat-icon"><i class="bi {{ $c['icon'] }}"></i></div>
                    <div>
                        <div class="r-stat-label">{{ $c['label'] }}</div>
                        <div class="r-stat-value">{{ number_format($c['value'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="r-stat-sub">{{ $c['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Grafik --}}
    <div class="charts">
        <div class="card chart-card">
            <div class="chart-head">
                <h3>Grafik Peminjaman Buku</h3>
                <select name="grafik" form="filter-form" onchange="this.form.submit()" class="select-sm">
                    <option value="7"  @selected($grafik == 7)>7 Hari Terakhir</option>
                    <option value="30" @selected($grafik == 30)>30 Hari Terakhir</option>
                </select>
            </div>
            <div class="chart-box"><canvas id="chartBar"></canvas></div>
        </div>

        <div class="card chart-card">
            <div class="chart-head"><h3>Kategori Buku Terbanyak Dipinjam</h3></div>
            <div class="donut-wrap">
                <div class="donut-box">
                    <canvas id="chartDonut"></canvas>
                    <div class="donut-center">
                        <strong>{{ number_format($stat['peminjaman'], 0, ',', '.') }}</strong>
                        <span class="d-total">Total Peminjaman</span>
                        <span class="d-total-m">Total</span>
                    </div>
                </div>
                <ul class="legend">
                    @forelse ($kategori as $k)
                        <li>
                            <span class="dot" style="background: {{ $k['warna'] }}"></span>
                            <span class="lg-name">{{ $k['nama'] }}</span>
                            <span class="lg-pct">{{ $k['persen'] }}%</span>
                        </li>
                    @empty
                        <li class="muted">Belum ada data.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Tabel dengan tab --}}
    <div class="card report-card">
        @if ($tab === 'transaksi' && $ringkasTransaksi)
            <div class="trx-summary">
                <div class="trx-item">
                    <span class="trx-icon blue"><i class="bi bi-receipt"></i></span>
                    <div>
                        <span class="trx-label">Total Transaksi</span>
                        <strong>{{ number_format($ringkasTransaksi['total'], 0, ',', '.') }}</strong>
                    </div>
                </div>
                <div class="trx-item">
                    <span class="trx-icon orange"><i class="bi bi-cash-stack"></i></span>
                    <div>
                        <span class="trx-label">Total Denda</span>
                        <strong>Rp {{ number_format($ringkasTransaksi['total_denda'], 0, ',', '.') }}</strong>
                    </div>
                </div>
                <div class="trx-item">
                    <span class="trx-icon red"><i class="bi bi-exclamation-triangle"></i></span>
                    <div>
                        <span class="trx-label">Denda Belum Lunas</span>
                        <strong>Rp {{ number_format($ringkasTransaksi['belum'], 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>
        @endif

        <div class="tabs-row">
            <div class="tabs">
                @foreach (['peminjaman' => 'Peminjaman Terbaru', 'pengembalian' => 'Pengembalian Terbaru', 'keterlambatan' => 'Keterlambatan', 'transaksi' => 'Transaksi & Denda'] as $key => $label)
                    <a href="{{ route($area.'.laporan', array_merge(request()->query(), ['tab' => $key, 'page' => null])) }}"
                       class="tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            <button type="button" class="btn btn-outline btn-print" onclick="window.print()">
                <i class="bi bi-printer"></i> Cetak Laporan
            </button>
        </div>

        <table class="table report-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Anggota</th>
                    <th class="hide-m">Judul Buku</th>
                    <th class="hide-m">{{ $kolom[0] }}</th>
                    <th class="hide-m">{{ $kolom[1] }}</th>
                    <th>Status</th>
                    @if ($tab === 'transaksi')
                        <th>Denda</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $row)
                    <tr>
                        <td>{{ $rows->firstItem() + $i }}</td>
                        <td>{{ $row['anggota'] }}</td>
                        <td class="hide-m">{{ $row['buku'] }}</td>
                        <td class="hide-m">{{ $row['tgl1'] }}</td>
                        <td class="hide-m">{{ $row['tgl2'] }}</td>
                        <td><span class="badge badge-pill {{ $row['kelas'] }}">{{ $row['status'] }}</span></td>
                        @if ($tab === 'transaksi')
                            <td>
                                @if (($row['denda'] ?? 0) > 0)
                                    Rp {{ number_format($row['denda'], 0, ',', '.') }}
                                    <span class="badge badge-pill {{ ($row['status_bayar'] ?? 'Belum Lunas') === 'Lunas' ? 'badge-success' : 'badge-danger' }}">{{ $row['status_bayar'] ?? 'Belum Lunas' }}</span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $tab === 'transaksi' ? 7 : 6 }}" class="empty">Tidak ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="table-footer">
            <span class="muted">
                Menampilkan {{ $rows->firstItem() ?? 0 }} – {{ $rows->lastItem() ?? 0 }}
                dari {{ number_format($rows->total(), 0, ',', '.') }} data
            </span>
            <span class="pg-desktop">{{ $rows->onEachSide(1)->links('partials.pagination') }}</span>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Rentang tanggal
    flatpickr('#rentang', {
        mode: 'range',
        locale: 'id',
        altInput: true,
        altFormat: 'd M Y',
        dateFormat: 'Y-m-d',
        defaultDate: [@json($mulai->format('Y-m-d')), @json($selesai->format('Y-m-d'))],
        onChange: function (dates, str, inst) {
            if (dates.length === 2) {
                document.getElementById('mulai').value = inst.formatDate(dates[0], 'Y-m-d');
                document.getElementById('selesai').value = inst.formatDate(dates[1], 'Y-m-d');
                document.getElementById('filter-form').submit();
            }
        }
    });

    // Grafik batang
    new Chart(document.getElementById('chartBar'), {
        type: 'bar',
        data: {
            labels: @json($grafikLabel),
            datasets: [{
                data: @json($grafikData),
                backgroundColor: '#5b9bec',
                borderRadius: 4,
                maxBarThickness: 28
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6b86a6' } },
                y: { beginAtZero: true, grid: { color: '#e3edf8' }, ticks: { color: '#6b86a6', precision: 0 } }
            }
        }
    });

    // Donut kategori
    new Chart(document.getElementById('chartDonut'), {
        type: 'doughnut',
        data: {
            labels: @json(collect($kategori)->pluck('nama')),
            datasets: [{
                data: @json(collect($kategori)->pluck('total')),
                backgroundColor: @json(collect($kategori)->pluck('warna')),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '66%',
            plugins: { legend: { display: false } }
        }
    });
</script>
@endpush

@extends('layouts.master')

@section('title', 'Statistik')
@section('role', 'admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/laporan.css') }}">
@endpush

@section('content')
    @php
        $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $area = (auth()->user()->role ?? null) === 'Pustakawan' ? 'pustakawan' : 'admin';
    @endphp

    {{-- Header halaman --}}
    <div class="page-header">
        <div class="page-title">
            <i class="bi bi-graph-up-arrow"></i>
            <div>
                <h1>Statistik Perpustakaan</h1>
                <p>Ringkasan data koleksi, sirkulasi, dan anggota perpustakaan.</p>
            </div>
        </div>

        <form method="GET" action="{{ route($area . '.statistik') }}" class="field field-date range-field">
            <i class="bi bi-calendar3"></i>
            <select name="grafik" onchange="this.form.submit()" class="select-sm" style="border:0;background:transparent;font-family:inherit;font-size:13.5px;color:inherit;outline:none;width:100%;">
                <option value="7" @selected($grafik == 7)>7 Hari Terakhir</option>
                <option value="30" @selected($grafik == 30)>30 Hari Terakhir</option>
            </select>
        </form>
    </div>

    {{-- Statistik ringkasan --}}
    <div class="r-stats">
        @php
            $cards = [
                ['icon' => 'bi-book',           'label' => 'Total Buku',       'value' => $stat['buku'],      'sub' => 'Data seluruh koleksi'],
                ['icon' => 'bi-people-fill',    'label' => 'Total Anggota',    'value' => $stat['anggota'],   'sub' => 'Data seluruh anggota'],
                ['icon' => 'bi-journal-arrow-up','label' => 'Sedang Dipinjam', 'value' => $stat['dipinjam'],  'sub' => 'Transaksi aktif'],
                ['icon' => 'bi-exclamation-triangle-fill', 'label' => 'Terlambat', 'value' => $stat['terlambat'], 'sub' => 'Lewat jatuh tempo'],
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

    {{-- Grafik utama --}}
    <div class="charts">
        <div class="card chart-card">
            <div class="chart-head"><h3>Tren Peminjaman Buku</h3></div>
            <div class="chart-box"><canvas id="chartBar"></canvas></div>
        </div>

        <div class="card chart-card">
            <div class="chart-head"><h3>Kategori Buku Terpopuler</h3></div>
            <div class="donut-wrap">
                <div class="donut-box">
                    <canvas id="chartDonut"></canvas>
                    <div class="donut-center">
                        <strong>{{ number_format(collect($kategori)->sum('total'), 0, ',', '.') }}</strong>
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

    {{-- Grafik sekunder --}}
    <div class="charts">
        <div class="card chart-card">
            <div class="chart-head"><h3>Peminjaman per Kelas</h3></div>
            <div class="chart-box"><canvas id="chartKelas"></canvas></div>
        </div>

        <div class="card chart-card">
            <div class="chart-head"><h3>Ringkasan Denda &amp; Status</h3></div>
            <div class="denda-mini">
                <div class="denda-mini-item">
                    <span><i class="bi bi-cash-stack"></i> Total Denda</span>
                    <strong>{{ $rupiah($denda['total']) }}</strong>
                </div>
                <div class="denda-mini-item">
                    <span><i class="bi bi-exclamation-circle"></i> Denda Belum Lunas</span>
                    <strong>{{ $rupiah($denda['belum']) }}</strong>
                </div>
                <div class="denda-mini-item">
                    <span><i class="bi bi-hourglass-split"></i> Menunggu Persetujuan</span>
                    <strong>{{ number_format($stat['menunggu'], 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Buku terpopuler --}}
    <div class="card report-card">
        <div class="chart-head" style="margin-bottom:12px;"><h3>Buku Terpopuler</h3></div>
        <table class="table report-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Judul Buku</th>
                    <th class="hide-m">Kategori</th>
                    <th class="hide-m">Pengarang</th>
                    <th>Dipinjam</th>
                    <th>Stok</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($populer as $i => $b)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $b->judul }}</strong></td>
                        <td class="hide-m">{{ $b->kategori }}</td>
                        <td class="hide-m">{{ $b->pengarang ?: '-' }}</td>
                        <td><span class="badge-pill badge-info">{{ number_format($b->total, 0, ',', '.') }}x</span></td>
                        <td>{{ $b->stok }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada data peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Tren peminjaman
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
        options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { display: false } } }
    });

    // Peminjaman per kelas
    new Chart(document.getElementById('chartKelas'), {
        type: 'bar',
        data: {
            labels: @json($kelasLabel),
            datasets: [{
                data: @json($kelasData),
                backgroundColor: '#34c38f',
                borderRadius: 4,
                maxBarThickness: 24
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#e3edf8' }, ticks: { color: '#6b86a6', precision: 0 } },
                y: { grid: { display: false }, ticks: { color: '#6b86a6' } }
            }
        }
    });
</script>
@endpush

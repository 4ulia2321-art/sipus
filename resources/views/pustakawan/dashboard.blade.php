@extends('layouts.master')

@section('title', 'Dashboard Pustakawan')
@section('role', 'admin')

@section('content')
<div class="pd-dash">
    <div class="page-head">
        <div>
            <h2>Selamat Datang, {{ $nama }}</h2>
            <p>Pantau dan kelola seluruh kegiatan perpustakaan dengan mudah melalui sistem SIPUS.</p>
        </div>
        <div class="date-chip">
            <i class="bi bi-calendar3"></i>
            {{ \Carbon\Carbon::now()->locale('id')->translatedFormat('l, j F Y') }}
        </div>
    </div>

    {{-- Kartu statistik --}}
    <section class="stats" data-live-part="stats" data-autorefresh="30000">
        @foreach ($stats as $s)
            <div class="stat-card {{ $s['color'] }}">
                <div class="stat-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                <h4>{{ $s['label'] }}</h4>
                <div class="stat-value">{{ number_format($s['value'], 0, ',', '.') }}</div>
                <div class="stat-delta"><i class="bi bi-arrow-up"></i> <b>{{ $s['delta'] }}</b> {{ $s['delta_text'] }}</div>
            </div>
        @endforeach
    </section>

    <div class="grid-2">
        {{-- Kolom kiri --}}
        <div class="col">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-bar-chart-fill"></i>
                    <div>
                        <h3>Grafik Peminjaman Buku</h3>
                        <small>7 hari terakhir</small>
                    </div>
                </div>
                <div class="chart-wrap"><canvas id="chartPeminjaman"></canvas></div>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="bi bi-clock"></i>
                    <h3>Aktivitas Terbaru</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr><th>Waktu</th><th>Nama</th><th>Aktivitas</th><th>Keterangan</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($aktivitas as $a)
                            <tr>
                                <td>{{ $a['waktu'] }}</td>
                                <td>{{ $a['nama'] }}</td>
                                <td>{{ $a['aktivitas'] }}</td>
                                <td>{{ $a['keterangan'] }}</td>
                                <td>
                                    <span class="badge {{ $a['aktivitas'] === 'Peminjaman' ? 'green' : ($a['aktivitas'] === 'Pengembalian' ? 'blue' : 'purple') }}">
                                        {{ $a['badge'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">Belum ada aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <a href="{{ route($area.'.peminjaman') }}" class="link-more">Lihat semua aktivitas <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>

        {{-- Kolom kanan --}}
        <div class="col">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-people-fill"></i>
                    <h3>Kategori Anggota</h3>
                </div>

                @php
                    $total  = collect($kategori)->sum('jumlah');
                    $start  = 0;
                    $stops  = [];
                    foreach ($kategori as $k) {
                        $end = $start + ($total ? $k['jumlah'] / $total * 100 : 0);
                        $stops[] = "{$k['color']} {$start}% {$end}%";
                        $start = $end;
                    }
                @endphp

                <div class="donut-wrap">
                    <div class="donut" style="background: conic-gradient({{ implode(', ', $stops) }});">
                        <div class="donut-hole">
                            <small>Total</small>
                            <strong>{{ $total }}</strong>
                            <small>anggota</small>
                        </div>
                    </div>
                    <ul class="legend">
                        @foreach ($kategori as $k)
                            <li>
                                <span class="dot" style="background: {{ $k['color'] }}"></span>
                                <span class="name">{{ $k['nama'] }}</span>
                                <span class="val">{{ $k['jumlah'] }} ({{ number_format($total ? $k['jumlah'] / $total * 100 : 0, 1, ',', '.') }}%)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <h3>Menu Cepat</h3>
                </div>

                <div class="quick-grid">
                    @foreach ($menuCepat as $q)
                        <a href="{{ Route::has($q['route']) ? route($q['route']) : '#' }}" class="quick {{ $q['color'] }}">
                            <span class="q-icon"><i class="bi {{ $q['icon'] }}"></i></span>
                            <span class="q-label">{{ $q['label'] }}</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    @endforeach
                </div>

                <div class="announce">
                    <i class="bi bi-megaphone-fill"></i>
                    <div>
                        <div class="announce-head">
                            <strong>Pengumuman</strong>
                            <small>{{ $pengumuman['tanggal'] }}</small>
                        </div>
                        <p>{{ $pengumuman['isi'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const labels = @json($grafik['labels']);
    const data   = @json($grafik['data']);
    const ctx    = document.getElementById('chartPeminjaman').getContext('2d');

    const gradient = ctx.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(37, 99, 235, .25)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: '#2563eb',
                backgroundColor: gradient,
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: '#2563eb',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 24 } },
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 0, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef2f7' } },
                x: { grid: { display: false } }
            }
        },
        plugins: [{
            id: 'valueLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                ctx.save();
                ctx.font = '500 12px Poppins, sans-serif';
                ctx.fillStyle = '#1e293b';
                ctx.textAlign = 'center';
                chart.getDatasetMeta(0).data.forEach((pt, i) => {
                    ctx.fillText(data[i], pt.x, pt.y - 10);
                });
                ctx.restore();
            }
        }]
    });
</script>
@endpush

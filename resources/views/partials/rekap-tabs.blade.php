{{-- Navigasi gabungan menu Rekap Data: rekap agregat, statistik, dan laporan keseluruhan. --}}
<div class="card report-tabs-card">
    <div class="tabs-row">
        <div class="tabs">
            <a href="{{ route('admin.rekap') }}"
               class="tab {{ request()->routeIs('admin.rekap*') ? 'active' : '' }}">Rekap Data</a>
            <a href="{{ route('admin.statistik') }}"
               class="tab {{ request()->routeIs('admin.statistik*') ? 'active' : '' }}">Statistik</a>
            <a href="{{ route('admin.laporan') }}"
               class="tab {{ request()->routeIs('admin.laporan*') ? 'active' : '' }}">Laporan Keseluruhan</a>
        </div>
    </div>
</div>

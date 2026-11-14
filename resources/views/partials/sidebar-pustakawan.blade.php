<aside class="sidebar">
    <div>
        <a href="{{ route('pustakawan.dashboard') }}" class="sidebar-brand">
            <i class="bi bi-book brand-logo"></i>
            <div class="brand-text">
                <strong>SIPUS</strong>
                <small>Sistem Informasi Perpustakaan<br>SMP Negeri 01 Pasirjambu</small>
            </div>
        </a>

        <nav class="sidebar-menu">
            <a href="{{ route('pustakawan.dashboard') }}" class="{{ request()->routeIs('pustakawan.dashboard') ? 'active' : '' }}">
                <i class="bi {{ request()->routeIs('pustakawan.dashboard') ? 'bi-house-door-fill' : 'bi-house-door' }}"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('pustakawan.pengajuan') }}" class="{{ request()->routeIs('pustakawan.pengajuan*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-check"></i> <span>Pengajuan Peminjaman</span>
            </a>
            <a href="{{ route('pustakawan.persetujuan') }}" class="{{ request()->routeIs('pustakawan.persetujuan*') ? 'active' : '' }}">
                <i class="bi bi-check2-square"></i> <span>Persetujuan Peminjaman</span>
            </a>
            <a href="{{ route('pustakawan.peminjaman') }}" class="{{ request()->routeIs('pustakawan.peminjaman*') && (request()->routeIs('pustakawan.keterlambatan') === false) && (request('status') !== 'terlambat' || request('tab') === 'riwayat') && request('asal') !== 'keterlambatan' ? 'active' : '' }}">
                <i class="bi bi-file-earmark-plus"></i> <span>Transaksi Peminjaman</span>
            </a>
            <a href="{{ route('pustakawan.pengembalian') }}" class="{{ request()->routeIs('pustakawan.pengembalian*') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i> <span>Pengembalian</span>
            </a>
            <a href="{{ route('pustakawan.keterlambatan') }}" class="{{ request()->routeIs('pustakawan.keterlambatan*') || (request()->routeIs('pustakawan.peminjaman*') && ((request('status') === 'terlambat' && request('tab') !== 'riwayat') || request('asal') === 'keterlambatan')) ? 'active' : '' }}">
                <i class="bi bi-exclamation-triangle"></i> <span>Keterlambatan</span>
            </a>
            <a href="{{ route('pustakawan.denda') }}" class="{{ request()->routeIs('pustakawan.denda*') ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i> <span>Denda</span>
            </a>
            <a href="{{ route('pustakawan.laporan') }}" class="{{ request()->routeIs('pustakawan.laporan*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-data"></i> <span>Laporan Peminjaman dan Pengembalian</span>
            </a>

            <a href="{{ route('profil') }}" class="{{ request()->routeIs('profil*') ? 'active' : '' }}">
                <i class="bi bi-person-circle"></i> <span>Profil</span>
            </a>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="menu-btn">
                    <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
                </button>
            </form>
        </nav>
    </div>
</aside>

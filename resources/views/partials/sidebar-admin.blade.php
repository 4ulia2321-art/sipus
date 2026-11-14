<aside class="sidebar">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <i class="bi bi-book brand-logo"></i>
            <div class="brand-text">
                <strong>SIPUS</strong>
                <small>Sistem Informasi Perpustakaan<br>SMP Negeri 01 Pasirjambu</small>
            </div>
        </a>

        <nav class="sidebar-menu">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi {{ request()->routeIs('admin.dashboard') ? 'bi-house-door-fill' : 'bi-house-door' }}"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('admin.buku') }}" class="{{ request()->routeIs('admin.buku*') ? 'active' : '' }}">
                <i class="bi bi-book"></i> <span>Data Buku</span>
            </a>
            <a href="{{ route('admin.kelas') }}" class="{{ request()->routeIs('admin.kelas*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i> <span>Kelas</span>
            </a>
            <a href="{{ route('admin.akun') }}" class="{{ request()->routeIs('admin.akun*') ? 'active' : '' }}">
                <i class="bi bi-person-gear"></i> <span>Pengguna</span>
            </a>
            <a href="{{ route('admin.ketentuan-denda') }}" class="{{ request()->routeIs('admin.ketentuan-denda*') ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i> <span>Ketentuan Denda</span>
            </a>
            <a href="{{ route('admin.pengaturan') }}" class="{{ request()->routeIs('admin.pengaturan*') ? 'active' : '' }}">
                <i class="bi bi-gear"></i> <span>Pengaturan Sistem</span>
            </a>
            <a href="{{ route('admin.statistik') }}" class="{{ request()->routeIs('admin.statistik*') ? 'active' : '' }}">
                <i class="bi bi-graph-up-arrow"></i> <span>Statistik</span>
            </a>
            <a href="{{ route('admin.laporan') }}" class="{{ request()->routeIs('admin.laporan*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart"></i> <span>Laporan Keseluruhan</span>
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

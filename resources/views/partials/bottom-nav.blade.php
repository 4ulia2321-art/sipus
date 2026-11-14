@php
    if ($isAdmin && (auth()->user()->role ?? '') === 'Pustakawan') {
        // Menu bawah khusus Pustakawan (operasional inti).
        $navItems = [
            ['pustakawan.dashboard',    'Beranda',     'bi-house-door',         'pustakawan.dashboard'],
            ['pustakawan.pengajuan',    'Pengajuan',   'bi-file-earmark-check', 'pustakawan.pengajuan*'],
            ['pustakawan.persetujuan',  'Persetujuan', 'bi-check2-square',      'pustakawan.persetujuan*'],
            ['pustakawan.peminjaman',   'Peminjaman',  'bi-file-earmark-plus',  'pustakawan.peminjaman*'],
            ['pustakawan.pengembalian', 'Pengembalian','bi-clock-history',      'pustakawan.pengembalian*'],
        ];
    } elseif ($isAdmin) {
        $navItems = [
            ['admin.dashboard', 'Beranda',   'bi-house-door',   'admin.dashboard'],
            ['admin.buku',      'Buku',      'bi-book',         'admin.buku*'],
            ['admin.statistik', 'Statistik', 'bi-graph-up-arrow','admin.statistik*'],
            ['admin.laporan',   'Laporan',   'bi-bar-chart',    'admin.laporan*'],
        ];
    } else {
        // Item ke-4 berganti antara Riwayat dan Notifikasi, sesuai halaman yang sedang dibuka.
        $keempat = request()->routeIs('notifikasi*')
            ? ['notifikasi', 'Notifikasi', 'bi-bell', 'notifikasi*']
            : ['riwayat', 'Riwayat', 'bi-clock-history', 'riwayat*'];

        $navItems = [
            ['dashboard', 'Beranda',    'bi-house-door',        'dashboard'],
            ['katalog',   'Katalog',    'bi-book',              ['katalog', 'buku.*']],
            ['pengajuan', 'Peminjaman', 'bi-file-earmark-plus', 'pengajuan*'],
            $keempat,
            ['profil',    'Profil',     'bi-person',            'profil'],
        ];
    }
@endphp

<nav class="bottom-nav" aria-label="Navigasi utama">
    @foreach ($navItems as [$rute, $label, $ikon, $pola])
        @php
            $aktif = request()->routeIs($pola);

            // Peminjaman yang difilter "terlambat" ditandai sebagai menu Keterlambatan.
            if ($rute === 'pustakawan.keterlambatan') {
                $aktif = $aktif || (request()->routeIs('pustakawan.peminjaman*') && request('status') === 'terlambat' && request('tab') !== 'riwayat');
            }
            if ($rute === 'pustakawan.peminjaman') {
                $aktif = $aktif && (request('status') !== 'terlambat' || request('tab') === 'riwayat') && request('asal') !== 'keterlambatan';
            }
        @endphp
        <a href="{{ route($rute) }}" class="{{ $aktif ? 'active' : '' }}">
            <i class="bi {{ $ikon }}"></i>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>

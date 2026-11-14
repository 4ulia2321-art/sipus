@extends('layouts.master')

@section('title', 'Pengembalian Buku')
@section('role', 'pustakawan')

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('pustakawan.dashboard') }}">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <span>Pengembalian</span>
    </nav>

    {{-- Page header --}}
    <div class="page-header">
        <div class="page-title">
            <i class="bi bi-arrow-repeat"></i>
            <div>
                <h1>Pengembalian Buku</h1>
                <p>Kelola proses pengembalian buku dari anggota perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route('pustakawan.pengembalian.create') }}" class="btn btn-primary btn-desktop">
            <i class="bi bi-plus-lg"></i> Input Pengembalian Manual
        </a>
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

    {{-- Filter --}}
    <form method="GET" action="{{ route('pustakawan.pengembalian') }}" class="filters" data-live-form>
        <div class="loan-tabs" role="tablist" aria-label="Filter pengembalian">
            <a href="{{ route('pustakawan.pengembalian', ['tab' => 'aktif']) }}"
               class="loan-tab {{ $tab === 'aktif' ? 'active' : '' }}">
                <i class="bi bi-journal-arrow-up"></i>
                Peminjaman Aktif
                <span class="loan-tab-count">{{ $totalAktif }}</span>
            </a>
            <a href="{{ route('pustakawan.pengembalian', ['tab' => 'riwayat']) }}"
               class="loan-tab {{ $tab === 'riwayat' ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i>
                Riwayat Pengembalian
                <span class="loan-tab-count">{{ $pengembalian->total() }}</span>
            </a>
        </div>
        <input type="hidden" name="tab" value="{{ $tab }}">

        @if ($tab === 'riwayat')
            <div class="field field-date">
                <i class="bi bi-calendar3"></i>
                <div>
                    <label>Rentang Tanggal</label>
                    <input type="text" name="tanggal" value="{{ request('tanggal') }}" placeholder="01 Jan 2026 - 31 Jan 2026">
                </div>
            </div>

            <div class="field">
                <div>
                    <label>Status Pengembalian</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="tepat_waktu" @selected(request('status') == 'tepat_waktu')>Tepat Waktu</option>
                        <option value="terlambat" @selected(request('status') == 'terlambat')>Terlambat</option>
                    </select>
                </div>
            </div>
        @endif

        <div class="field">
            <div>
                <label>Anggota</label>
                <select name="anggota" onchange="this.form.submit()">
                    <option value="">Semua Anggota</option>
                    @foreach ($anggota as $a)
                        <option value="{{ $a->id }}" @selected(request('anggota') == $a->id)>{{ $a->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field field-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama anggota / judul buku...">
        </div>
    </form>

    <a href="{{ route('pustakawan.pengembalian.create') }}" class="btn btn-primary btn-block btn-mobile">
        <i class="bi bi-plus-lg"></i> Input Pengembalian Manual
    </a>

    {{-- Tabel (desktop) --}}
    <div class="card table-card" data-live-part="list">
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="check-all"></th>
                    <th>No</th>
                    <th>Tanggal Kembali</th>
                    <th>Nama Anggota</th>
                    <th>Judul Buku</th>
                    <th>Tanggal Pinjam</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 0; @endphp

                @if ($tab === 'aktif' && $antrean->isNotEmpty())
                    <tr>
                        <td colspan="8" style="background: #f8fafc; font-weight: 700; font-size: 12px; color: var(--ink);">
                            PEMINJAMAN AKTIF
                            <span class="muted" style="font-weight: 400;">— {{ $totalAktif }} belum dicatat kembali</span>
                            @if ($totalAktif > $antrean->count())
                                <a href="{{ route('pustakawan.peminjaman', ['status' => 'dipinjam']) }}" class="link-all" style="margin-left: 8px;">
                                    Lihat semua
                                </a>
                            @endif
                        </td>
                    </tr>

                    @foreach ($antrean as $row)
                        <tr>
                            <td></td>
                            <td class="text-blue">{{ ++$no }}</td>
                            <td class="muted">Belum dikembalikan</td>
                            <td>
                                <div class="person">
                                    <span class="avatar avatar-sm"><i class="bi bi-person"></i></span>
                                    <div>
                                        <span class="text-blue strong">{{ $row->nama_siswa }}</span>
                                        <div class="muted">Kelas {{ $row->kelas }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="strong">{{ $row->judul }}</div>
                                <div class="muted">{{ $row->pengarang }}</div>
                            </td>
                            <td class="muted">{{ \Carbon\Carbon::parse($row->tanggal_pinjam)->translatedFormat('d M Y') }}</td>
                            <td>
                                @if ($row->menunggu_pengembalian)
                                    <span class="status-badge status-menunggu-pengembalian">Menunggu Pengembalian</span>
                                @elseif ($row->terlambat)
                                    <span class="status-badge status-terlambat">Terlambat</span>
                                @else
                                    <span class="status-badge status-dipinjam">Dipinjam</span>
                                @endif
                            </td>
                            <td class="table-actions">
                                <form action="{{ route('pustakawan.peminjaman.kembalikan', $row->id_peminjaman) }}" method="POST"
                                      onsubmit="return confirm(@js('Catat pengembalian ' . $row->judul . ' oleh ' . $row->nama_siswa . ' hari ini?'))">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-arrow-return-left"></i> Kembalikan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @endif

                @if ($tab === 'riwayat')
                    @if ($pengembalian->isNotEmpty())
                        <tr>
                            <td colspan="8" style="background: #f8fafc; font-weight: 700; font-size: 12px; color: var(--ink);">
                                RIWAYAT PENGEMBALIAN
                            </td>
                        </tr>
                    @endif

                    @foreach ($pengembalian as $i => $row)
                        <tr>
                            <td><input type="checkbox" class="check-row" value="{{ $row->id }}"></td>
                            <td class="text-blue">{{ ++$no }}</td>
                            <td>
                                <div class="muted">{{ $row->tanggal_kembali->translatedFormat('d M Y') }}</div>
                                <div class="muted">{{ $row->tanggal_kembali->format('H:i') }}</div>
                            </td>
                            <td>
                                <div class="person">
                                    <span class="avatar avatar-sm"><i class="bi bi-person"></i></span>
                                    <div>
                                        <span class="text-blue strong">{{ $row->anggota->nama }}</span>
                                        <div class="muted">Kelas {{ $row->anggota->kelas }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="strong">{{ $row->buku->judul }}</div>
                                <div class="muted">{{ $row->buku->penulis }}</div>
                            </td>
                            <td class="muted">{{ $row->tanggal_pinjam->translatedFormat('d M Y') }}</td>
                            <td>
                                <span class="status-badge status-{{ $row->terlambat ? 'terlambat' : 'dikembalikan' }}">
                                    {{ $row->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
                                </span>
                            </td>
                            <td class="table-actions">
                                <a href="{{ route('pustakawan.pengembalian.show', $row->id) }}" class="btn btn-outline btn-sm">
                                    <i class="bi bi-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @endforeach
                @endif

                @if ($tab === 'aktif' && $antrean->isEmpty())
                    <tr><td colspan="8" class="empty">Belum ada peminjaman aktif yang menunggu dikembalikan.</td></tr>
                @elseif ($tab === 'riwayat' && $pengembalian->isEmpty())
                    <tr><td colspan="8" class="empty">Belum ada riwayat pengembalian.</td></tr>
                @endif
            </tbody>
        </table>

        <div class="table-footer">
            @if ($tab === 'aktif')
                <span class="muted">{{ $antrean->count() }} peminjaman aktif</span>
            @else
                <span class="muted">
                    riwayat {{ $pengembalian->firstItem() ?? 0 }} - {{ $pengembalian->lastItem() ?? 0 }} dari {{ $pengembalian->total() }} data
                </span>
                {{ $pengembalian->onEachSide(1)->links('partials.pagination') }}
            @endif
        </div>
    </div>

    {{-- Kartu (mobile) --}}
    <div class="card-list" data-live-part="list">
        @if ($tab === 'aktif' && $antrean->isNotEmpty())
            <div class="muted" style="font-weight: 700; font-size: 12px; color: var(--ink); padding: 0 2px;">
                PEMINJAMAN AKTIF
                <span class="muted" style="font-weight: 400;">— {{ $totalAktif }} belum dicatat kembali</span>
            </div>

            @foreach ($antrean as $row)
                <div class="card item-card">
                    <span class="avatar avatar-sm"><i class="bi bi-person"></i></span>
                    <div class="item-body">
                        <span class="text-blue strong">{{ $row->nama_siswa }}</span>
                        <div class="muted">Kelas {{ $row->kelas }}</div>
                        <div class="strong mt">{{ $row->judul }}</div>
                        <div class="muted">{{ $row->pengarang }}</div>
                        <div class="muted dates">
                            <i class="bi bi-calendar3"></i>
                            Pinjam {{ \Carbon\Carbon::parse($row->tanggal_pinjam)->translatedFormat('d M Y') }}
                            &bull; Jatuh tempo {{ \Carbon\Carbon::parse($row->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}
                        </div>
                    </div>
                    <div class="item-side">
                        <form action="{{ route('pustakawan.peminjaman.kembalikan', $row->id_peminjaman) }}" method="POST"
                              onsubmit="return confirm(@js('Catat pengembalian ' . $row->judul . ' oleh ' . $row->nama_siswa . ' hari ini?'))">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-return-left"></i> Kembalikan
                            </button>
                        </form>
                        @if ($row->menunggu_pengembalian)
                            <span class="status-badge status-menunggu-pengembalian">Menunggu Pengembalian</span>
                        @elseif ($row->terlambat)
                            <span class="status-badge status-terlambat">Terlambat</span>
                        @else
                            <span class="status-badge status-dipinjam">Dipinjam</span>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        @if ($tab === 'riwayat' && $pengembalian->isNotEmpty())
            <div class="muted" style="font-weight: 700; font-size: 12px; color: var(--ink); padding: 8px 2px 0;">
                RIWAYAT PENGEMBALIAN
            </div>

            @foreach ($pengembalian as $row)
                <div class="card item-card">
                    <span class="avatar avatar-sm"><i class="bi bi-person"></i></span>
                    <div class="item-body">
                        <span class="text-blue strong">{{ $row->anggota->nama }}</span>
                        <div class="muted">Kelas {{ $row->anggota->kelas }}</div>
                        <div class="strong mt">{{ $row->buku->judul }}</div>
                        <div class="muted">{{ $row->buku->penulis }}</div>
                        <div class="muted dates">
                            <i class="bi bi-calendar3"></i>
                            {{ $row->tanggal_pinjam->translatedFormat('d M Y') }} &bull;
                            {{ $row->batas_kembali->translatedFormat('d M Y') }}
                        </div>
                    </div>
                    <div class="item-side">
                        <a href="{{ route('pustakawan.pengembalian.show', $row->id) }}" class="btn btn-outline btn-sm">
                            <i class="bi bi-eye"></i> Detail
                        </a>
                        <span class="status-badge status-{{ $row->terlambat ? 'terlambat' : 'dikembalikan' }}">
                            {{ $row->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
                        </span>
                    </div>
                </div>
            @endforeach
        @endif

        @if ($tab === 'aktif' && $antrean->isEmpty())
            <div class="card" style="padding: 16px; text-align: center; color: var(--muted);">
                Belum ada peminjaman aktif yang menunggu dikembalikan.
            </div>
        @elseif ($tab === 'riwayat' && $pengembalian->isEmpty())
            <div class="card" style="padding: 16px; text-align: center; color: var(--muted);">
                Belum ada riwayat pengembalian.
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('check-all')?.addEventListener('change', e => {
        document.querySelectorAll('.check-row').forEach(c => c.checked = e.target.checked);
    });
</script>
@endpush

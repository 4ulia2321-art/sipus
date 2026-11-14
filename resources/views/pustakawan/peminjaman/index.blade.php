@extends('layouts.master')

@section('title', 'Peminjaman Buku')
@section('role', 'pustakawan')

@section('content')
    {{-- Kartu profil (mobile) --}}
    @include('partials.profile-card')

    {{-- Judul + tombol peminjaman baru --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-file-earmark-check"></i>
            <div>
                <h1>{{ $judulHalaman ?? 'Peminjaman Buku' }}</h1>
                <p>{{ $deskripsiHalaman ?? 'Kelola data peminjaman buku oleh anggota perpustakaan.' }}</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    {{-- Pencarian & filter --}}
    <form action="{{ ($khususTerlambat ?? false) ? route('pustakawan.keterlambatan') : route('pustakawan.peminjaman') }}" method="GET" class="filter-card" data-live-form>
        @unless ($khususTerlambat ?? false)
            <div class="loan-tabs" role="tablist" aria-label="Filter transaksi peminjaman">
                <a href="{{ route('pustakawan.peminjaman', ['tab' => 'aktif']) }}"
                   class="loan-tab {{ ($tab ?? 'aktif') === 'aktif' ? 'active' : '' }}">
                    <i class="bi bi-journal-arrow-up"></i>
                    Transaksi Aktif
                    <span class="loan-tab-count">{{ $totalAktif ?? 0 }}</span>
                </a>
                <a href="{{ route('pustakawan.peminjaman', ['tab' => 'riwayat']) }}"
                   class="loan-tab {{ ($tab ?? 'aktif') === 'riwayat' ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    Riwayat Transaksi
                    <span class="loan-tab-count">{{ $totalRiwayat ?? 0 }}</span>
                </a>
            </div>
            <input type="hidden" name="tab" value="{{ $tab ?? 'aktif' }}">
        @endunless

        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama anggota / judul buku...">
        </div>

        @unless ($khususTerlambat ?? false)
            <label class="select-field">
                <span>Status</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    @if (($tab ?? 'aktif') === 'riwayat')
                        @foreach (['tepat-waktu' => 'Tepat Waktu', 'terlambat' => 'Terlambat'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    @else
                        @foreach (['dipinjam' => 'Dipinjam', 'terlambat' => 'Terlambat', 'menunggu-pengembalian' => 'Menunggu Pengembalian'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    @endif
                </select>
            </label>
        @endunless

        <label class="select-field">
            <span>{{ ($tab ?? 'aktif') === 'riwayat' && ! ($khususTerlambat ?? false) ? 'Tanggal Kembali' : 'Tanggal' }}</span>
            <select name="tanggal" onchange="this.form.submit()">
                <option value="">Semua Tanggal</option>
                @foreach (['hari-ini' => 'Hari ini', '7-hari' => '7 hari terakhir', '30-hari' => '30 hari terakhir', 'bulan-ini' => 'Bulan ini'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('tanggal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <a href="{{ ($khususTerlambat ?? false) ? route('pustakawan.keterlambatan') : route('pustakawan.peminjaman', ['tab' => $tab ?? 'aktif']) }}" class="btn-reset-soft">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
        </a>

        {{-- Tombol peminjaman baru versi mobile --}}
        <a href="{{ route('pustakawan.peminjaman.create') }}" class="btn-navy mobile-only">
            <i class="bi bi-plus-lg"></i> Peminjaman Baru
        </a>
    </form>

    {{-- Tabel (desktop) --}}
    <div class="books-card" data-live-part="list">
        <div class="table-scroll books-table-wrap">
            <table class="book-table loan-table">
                <thead>
                    <tr>
                        <th class="col-check"><input type="checkbox" data-check-all aria-label="Pilih semua"></th>
                        <th class="col-no">No</th>
                        <th>Nama Anggota</th>
                        <th>Judul Buku</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        @if (($tab ?? 'aktif') === 'riwayat')
                            <th>Tanggal Kembali</th>
                        @endif
                        <th>Status</th>
                        @if (($tab ?? 'aktif') === 'riwayat')
                            <th>Denda</th>
                        @endif
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peminjaman as $i => $row)
                        <tr>
                            <td class="col-check"><input type="checkbox" name="ids[]" value="{{ $row->id }}" data-check-row aria-label="Pilih {{ $row->nama }}"></td>
                            <td>{{ $peminjaman->firstItem() + $i }}</td>
                            <td>
                                <div class="member-cell">
                                    @include('partials.photo-thumb', ['foto' => $row->foto, 'alt' => $row->nama])
                                    <div>
                                        <strong>{{ $row->nama }}</strong>
                                        <small>{{ $row->kelas }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="book-cell">
                                <strong>{{ $row->judul }}</strong>
                                <small>{{ $row->pengarang }}</small>
                            </td>
                            <td>{{ $row->tanggal_pinjam->translatedFormat('d M Y') }}</td>
                            <td>{{ $row->jatuh_tempo->translatedFormat('d M Y') }}</td>
                            @if (($tab ?? 'aktif') === 'riwayat')
                                <td>{{ $row->tanggal_kembali ? $row->tanggal_kembali->translatedFormat('d M Y') : '-' }}</td>
                            @endif
                            <td>
                                <div class="status-stack">
                                    <span class="status-badge status-{{ $row->status }}">{{ ucwords(str_replace('-', ' ', $row->status)) }}</span>
                                    @if (($tab ?? 'aktif') === 'riwayat')
                                        <span class="status-badge {{ $row->terlambat ? 'status-terlambat' : 'status-dikembalikan' }}">
                                            {{ $row->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
                                        </span>
                                    @endif
                                    @if ($row->perpanjangan_status === 'Menunggu')
                                        <span class="status-badge status-menunggu">Perpanjangan Menunggu</span>
                                    @elseif ($row->perpanjangan_status === 'Disetujui')
                                        <span class="status-badge status-dikembalikan">Diperpanjang</span>
                                    @elseif ($row->perpanjangan_status === 'Ditolak')
                                        <span class="status-badge status-ditolak">Perpanjangan Ditolak</span>
                                    @endif
                                </div>
                            </td>
                            @if (($tab ?? 'aktif') === 'riwayat')
                                <td>
                                    @if ($row->denda > 0)
                                        <strong>Rp {{ number_format($row->denda, 0, ',', '.') }}</strong>
                                    @else
                                        <span class="muted">Rp 0</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('pustakawan.peminjaman.show', $row->id) }}" class="icon-btn" title="Detail"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('pustakawan.peminjaman.edit', ($khususTerlambat ?? false) ? ['id' => $row->id, 'asal' => 'keterlambatan'] : $row->id) }}" class="icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                    @if (($tab ?? 'aktif') === 'aktif')
                                        @if ($row->perpanjangan_status === 'Menunggu')
                                            <form action="{{ route('pustakawan.peminjaman.perpanjangan.setujui', $row->id) }}" method="POST"
                                                  onsubmit="return confirm(@js('Setujui perpanjangan peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                                @csrf
                                                <button type="submit" class="icon-btn icon-btn-ok" title="Setujui perpanjangan">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('pustakawan.peminjaman.perpanjangan.tolak', $row->id) }}" method="POST"
                                                  onsubmit="return confirm(@js('Tolak perpanjangan peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                                @csrf
                                                <button type="submit" class="icon-btn icon-btn-danger" title="Tolak perpanjangan">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        @elseif (! $row->tanggal_kembali)
                                            <form action="{{ route('pustakawan.peminjaman.perpanjang', $row->id) }}" method="POST"
                                                  onsubmit="return confirm(@js('Perpanjang peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                                @csrf
                                                <button type="submit" class="icon-btn" title="Perpanjang" @disabled($row->status !== 'dipinjam')>
                                                    <i class="bi bi-clock"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($tab ?? 'aktif') === 'riwayat' ? 10 : 8 }}" class="empty">
                                {{ ($tab ?? 'aktif') === 'riwayat' ? 'Riwayat transaksi tidak ditemukan.' : 'Data peminjaman tidak ditemukan.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="books-footer">
            <span>Menampilkan {{ $peminjaman->firstItem() ?? 0 }} - {{ $peminjaman->lastItem() ?? 0 }} dari {{ $peminjaman->total() }} data</span>
            @if ($peminjaman->hasPages())
                {{ $peminjaman->links('partials.pagination') }}
            @endif
        </div>
    </div>

    {{-- Daftar kartu (mobile) --}}
    <div class="loan-rows" data-live-part="list">
        @foreach ($peminjaman as $row)
            <article class="loan-row">
                @include('partials.photo-thumb', ['foto' => $row->foto, 'alt' => $row->nama])
                <div class="loan-row-body">
                    <strong>{{ $row->nama }}</strong>
                    <small>{{ $row->kelas }}</small>
                    <span class="loan-book">{{ $row->judul }}</span>
                    <small>{{ $row->pengarang }}</small>
                    <small class="loan-dates">
                        <i class="bi bi-calendar3"></i>
                        {{ $row->tanggal_pinjam->translatedFormat('d M Y') }}
                        <i class="bi bi-arrow-right"></i>
                        {{ $row->jatuh_tempo->translatedFormat('d M Y') }}
                    </small>

                    @if (($tab ?? 'aktif') === 'riwayat')
                        <small class="loan-dates">
                            <i class="bi bi-calendar-check"></i>
                            Kembali: {{ $row->tanggal_kembali ? $row->tanggal_kembali->translatedFormat('d M Y') : '-' }}
                            <span class="muted">&middot; Denda: Rp {{ number_format($row->denda, 0, ',', '.') }}</span>
                        </small>
                    @endif

                    <div class="loan-row-bottom">
                        <div class="status-stack">
                            <span class="status-badge status-{{ $row->status }}">{{ ucwords(str_replace('-', ' ', $row->status)) }}</span>
                            @if (($tab ?? 'aktif') === 'riwayat')
                                <span class="status-badge {{ $row->terlambat ? 'status-terlambat' : 'status-dikembalikan' }}">
                                    {{ $row->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
                                </span>
                            @endif
                            @if ($row->perpanjangan_status === 'Menunggu')
                                <span class="status-badge status-menunggu">Perpanjangan Menunggu</span>
                            @elseif ($row->perpanjangan_status === 'Disetujui')
                                <span class="status-badge status-dikembalikan">Diperpanjang</span>
                            @elseif ($row->perpanjangan_status === 'Ditolak')
                                <span class="status-badge status-ditolak">Perpanjangan Ditolak</span>
                            @endif
                        </div>
                        <div class="row-actions">
                            <a href="{{ route('pustakawan.peminjaman.show', $row->id) }}" class="icon-btn sm" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('pustakawan.peminjaman.edit', ($khususTerlambat ?? false) ? ['id' => $row->id, 'asal' => 'keterlambatan'] : $row->id) }}" class="icon-btn sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            @if (($tab ?? 'aktif') === 'aktif')
                                @if ($row->perpanjangan_status === 'Menunggu')
                                    <form action="{{ route('pustakawan.peminjaman.perpanjangan.setujui', $row->id) }}" method="POST"
                                          onsubmit="return confirm(@js('Setujui perpanjangan peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                        @csrf
                                        <button type="submit" class="icon-btn sm icon-btn-ok" title="Setujui perpanjangan">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('pustakawan.peminjaman.perpanjangan.tolak', $row->id) }}" method="POST"
                                          onsubmit="return confirm(@js('Tolak perpanjangan peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                        @csrf
                                        <button type="submit" class="icon-btn sm icon-btn-danger" title="Tolak perpanjangan">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @elseif (! $row->tanggal_kembali)
                                    <form action="{{ route('pustakawan.peminjaman.perpanjang', $row->id) }}" method="POST"
                                          onsubmit="return confirm(@js('Perpanjang peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                        @csrf
                                        <button type="submit" class="icon-btn sm" title="Perpanjang" @disabled($row->status !== 'dipinjam')>
                                            <i class="bi bi-clock"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @endforeach

        @if ($peminjaman->hasPages())
            {{ $peminjaman->links('partials.pagination') }}
        @endif
    </div>
@endsection

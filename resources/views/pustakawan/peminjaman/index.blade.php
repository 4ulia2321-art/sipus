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
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama anggota / judul buku...">
        </div>

        @unless ($khususTerlambat ?? false)
            <label class="select-field">
                <span>Status</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    @foreach (['dipinjam' => 'Dipinjam', 'dikembalikan' => 'Dikembalikan', 'terlambat' => 'Terlambat'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endunless

        <label class="select-field">
            <span>Tanggal</span>
            <select name="tanggal" onchange="this.form.submit()">
                <option value="">Semua Tanggal</option>
                @foreach (['hari-ini' => 'Hari ini', '7-hari' => '7 hari terakhir', '30-hari' => '30 hari terakhir', 'bulan-ini' => 'Bulan ini'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('tanggal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <a href="{{ ($khususTerlambat ?? false) ? route('pustakawan.keterlambatan') : route('pustakawan.peminjaman') }}" class="btn-reset-soft">
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
                        <th>Status</th>
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
                            <td><span class="status-badge status-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('pustakawan.peminjaman.show', $row->id) }}" class="icon-btn" title="Detail"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('pustakawan.peminjaman.edit', ($khususTerlambat ?? false) ? ['id' => $row->id, 'asal' => 'keterlambatan'] : $row->id) }}" class="icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('pustakawan.peminjaman.perpanjang', $row->id) }}" method="POST"
                                          onsubmit="return confirm(@js('Perpanjang peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                        @csrf
                                        <button type="submit" class="icon-btn" title="Perpanjang" @disabled($row->status !== 'dipinjam')>
                                            <i class="bi bi-clock"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">Data peminjaman tidak ditemukan.</td></tr>
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

                    <div class="loan-row-bottom">
                        <span class="status-badge status-{{ $row->status }}">{{ ucfirst($row->status) }}</span>
                        <div class="row-actions">
                            <a href="{{ route('pustakawan.peminjaman.show', $row->id) }}" class="icon-btn sm" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('pustakawan.peminjaman.edit', ($khususTerlambat ?? false) ? ['id' => $row->id, 'asal' => 'keterlambatan'] : $row->id) }}" class="icon-btn sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('pustakawan.peminjaman.perpanjang', $row->id) }}" method="POST"
                                  onsubmit="return confirm(@js('Perpanjang peminjaman ' . $row->judul . ' oleh ' . $row->nama . '?'))">
                                @csrf
                                <button type="submit" class="icon-btn sm" title="Perpanjang" @disabled($row->status !== 'dipinjam')>
                                    <i class="bi bi-clock"></i>
                                </button>
                            </form>
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

@extends('layouts.master')

@section('title', 'Pengembalian Buku')

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Dashboard</a>
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
        <a href="{{ route('pengembalian.create') }}" class="btn btn-primary btn-desktop">
            <i class="bi bi-plus-lg"></i> Input Pengembalian Manual
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('pengembalian.index') }}" class="filters" data-live-form>
        <div class="field field-date">
            <i class="bi bi-calendar3"></i>
            <div>
                <label>Rentang Tanggal</label>
                <input type="text" name="tanggal" value="{{ request('tanggal', '01 Sep 2025 - 30 Sep 2025') }}">
            </div>
            <i class="bi bi-chevron-down"></i>
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

    <a href="{{ route('pengembalian.create') }}" class="btn btn-primary btn-block btn-mobile">
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
                @forelse ($pengembalian as $i => $row)
                    <tr>
                        <td><input type="checkbox" class="check-row" value="{{ $row->id }}"></td>
                        <td class="text-blue">{{ $pengembalian->firstItem() + $i }}</td>
                        <td>
                            <div class="muted">{{ $row->tanggal_kembali->translatedFormat('d M Y') }}</div>
                            <div class="muted">{{ $row->tanggal_kembali->format('H:i') }}</div>
                        </td>
                        <td>
                            <div class="person">
                                <img class="avatar avatar-sm" src="{{ $row->anggota->avatar_url ?? asset('images/avatar.png') }}" alt="">
                                <div>
                                    <a href="#" class="text-blue strong">{{ $row->anggota->nama }}</a>
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
                            <span class="badge {{ $row->terlambat ? 'badge-danger' : 'badge-success' }}">
                                {{ $row->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('pengembalian.show', $row->id) }}" class="btn btn-outline btn-sm">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Belum ada data pengembalian.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="table-footer">
            <span class="muted">
                Menampilkan {{ $pengembalian->firstItem() ?? 0 }} - {{ $pengembalian->lastItem() ?? 0 }}
                dari {{ $pengembalian->total() }} data
            </span>
            {{ $pengembalian->onEachSide(1)->links('partials.pagination') }}
        </div>
    </div>

    {{-- Kartu (mobile) --}}
    <div class="card-list" data-live-part="list">
        @foreach ($pengembalian as $row)
            <div class="card item-card">
                <img class="avatar avatar-sm" src="{{ $row->anggota->avatar_url ?? asset('images/avatar.png') }}" alt="">
                <div class="item-body">
                    <a href="#" class="text-blue strong">{{ $row->anggota->nama }}</a>
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
                    <a href="{{ route('pengembalian.show', $row->id) }}" class="btn btn-outline btn-sm">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                    <span class="badge {{ $row->terlambat ? 'badge-danger' : 'badge-success' }}">
                        {{ $row->terlambat ? 'Terlambat' : 'Tepat Waktu' }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('check-all')?.addEventListener('change', e => {
        document.querySelectorAll('.check-row').forEach(c => c.checked = e.target.checked);
    });
</script>
@endpush

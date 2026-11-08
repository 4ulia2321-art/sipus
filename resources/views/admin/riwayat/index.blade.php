@extends('layouts.master')

@section('title', 'Riwayat Peminjaman')

@section('content')
    <div class="greeting">
        <h1>Riwayat Peminjaman</h1>
        <p>Pantau semua peminjaman beserta statusnya.</p>
    </div>

    {{-- Filter --}}
    <form action="{{ route('riwayat') }}" method="GET" class="catalog-filter" data-live-form>
        <div class="filter-search-row">
            <div class="filter-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari judul buku, pengarang, atau nama siswa...">
                @if (request('q'))
                    <a href="{{ route('riwayat', request()->except('q', 'page')) }}" class="clear-search" title="Hapus pencarian">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
            <button type="submit" class="btn-search">Cari</button>
        </div>

        <div class="filter-select-row">
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                @foreach ($statusList as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>

            <select name="tahun" onchange="this.form.submit()">
                <option value="">Semua tahun</option>
                @foreach ($tahunList as $tahun)
                    <option value="{{ $tahun }}" @selected((string) request('tahun') === (string) $tahun)>{{ $tahun }}</option>
                @endforeach
            </select>
        </div>
    </form>

    {{-- Tabel riwayat --}}
    <section class="panel" data-live-part="list">
        <div class="panel-head">
            <h3>Daftar Peminjaman</h3>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Judul Buku</th>
                        @if ($isAdmin)
                            <th>Siswa</th>
                            <th>Kelas</th>
                        @endif
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Tanggal Kembali</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $i => $row)
                        <tr>
                            <td>{{ $riwayat->firstItem() + $i }}</td>
                            <td>{{ $row->judul }}<br><small>{{ $row->pengarang }}</small></td>
                            @if ($isAdmin)
                                <td>{{ $row->nama_siswa }}<br><small>{{ $row->nis }}</small></td>
                                <td>{{ $row->kelas }}</td>
                            @endif
                            <td>{{ \Carbon\Carbon::parse($row->tanggal_pinjam)->translatedFormat('d F Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->tanggal_jatuh_tempo)->translatedFormat('d F Y') }}</td>
                            <td>
                                {{ $row->tanggal_kembali ? \Carbon\Carbon::parse($row->tanggal_kembali)->translatedFormat('d F Y') : '-' }}
                            </td>
                            <td>
                                <span class="status-pill status-{{ strtolower($row->status_efektif) }}">
                                    {{ $row->status_efektif }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('buku.show', $row->buku_id) }}" class="btn-sm btn-ok">
                                    <i class="bi bi-eye"></i> Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isAdmin ? 8 : 6 }}" class="empty">Belum ada riwayat peminjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($riwayat->hasPages())
            {{ $riwayat->links('partials.pagination') }}
        @endif
    </section>
@endsection
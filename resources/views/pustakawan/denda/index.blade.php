@extends('layouts.master')

@section('title', 'Kelola Denda')
@section('role', 'pustakawan')
@section('search_placeholder', 'Cari nama siswa atau NIS...')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota.css') }}">
@endpush

@section('content')
    @php
        $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    @endphp

    {{-- Header halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-cash-coin"></i>
            <div>
                <h2>Kelola Denda</h2>
                <p>Pantau dan kelola denda keterlambatan pengembalian buku.</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
    @endif

    {{-- Kartu ringkasan --}}
    <section class="stats" data-live-part="stats">
        @php
            $cards = [
                ['icon' => 'bi-cash-stack',  'color' => 'stat-blue',   'label' => 'Total Denda',        'value' => $rupiah($stat['total_denda'])],
                ['icon' => 'bi-exclamation-triangle-fill', 'color' => 'stat-orange', 'label' => 'Belum Lunas', 'value' => $rupiah($stat['belum'])],
                ['icon' => 'bi-check-circle-fill', 'color' => 'stat-green', 'label' => 'Sudah Lunas', 'value' => $rupiah($stat['lunas'])],
                ['icon' => 'bi-receipt',     'color' => 'stat-purple', 'label' => 'Tagihan Aktif',      'value' => number_format($stat['jumlah_belum'], 0, ',', '.') . ' data'],
            ];
        @endphp
        @foreach ($cards as $s)
            <div class="stat-card {{ $s['color'] }}">
                <div class="stat-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                <h4>{{ $s['label'] }}</h4>
                <div class="stat-value">{{ $s['value'] }}</div>
                <div class="stat-delta"><i class="bi bi-info-circle"></i> Akumulasi denda</div>
            </div>
        @endforeach
    </section>

    {{-- Filter --}}
    <form method="GET" action="{{ route('pustakawan.denda') }}" class="filter-bar" data-live-form>
        <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama siswa atau NIS...">
        </div>

        <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach (['Belum Lunas', 'Lunas'] as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
            @endforeach
        </select>

        <label class="filter-check">
            <input type="checkbox" name="hanya_denda" value="1" onchange="this.form.submit()" @checked(request('hanya_denda') === '1')>
            Hanya yang berdenda
        </label>

        <a href="{{ route('pustakawan.denda') }}" class="btn btn-outline"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>

    {{-- Tabel --}}
    <div class="card table-card" data-live-part="list">
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Anggota</th>
                        <th>Kelas</th>
                        <th>Judul Buku</th>
                        <th>Tgl Kembali</th>
                        <th>Denda</th>
                        <th>Status</th>
                        <th class="t-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="judul">{{ $r->nama }}</div>
                                <div class="muted">{{ $r->nis }}</div>
                            </td>
                            <td>{{ $r->kelas ?? '-' }}</td>
                            <td class="judul">{{ $r->buku }}</td>
                            <td>{{ $r->tanggal_kembali ? \Carbon\Carbon::parse($r->tanggal_kembali)->translatedFormat('d M Y') : '-' }}</td>
                            <td>
                                @if ($r->denda > 0)
                                    <span class="strong">{{ $rupiah($r->denda) }}</span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="pill {{ $r->status_bayar === 'Lunas' ? 'green' : 'red' }}">{{ $r->status_bayar }}</span>
                            </td>
                            <td>
                                <div class="actions center">
                                    <a href="{{ route('pustakawan.denda.edit', $r->id) }}" class="act edit" title="Edit denda"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('pustakawan.denda.lunas', $r->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="act {{ $r->status_bayar === 'Lunas' ? 'del' : 'view' }}"
                                                title="{{ $r->status_bayar === 'Lunas' ? 'Batalkan lunas' : 'Tandai lunas' }}">
                                            <i class="bi {{ $r->status_bayar === 'Lunas' ? 'bi-x-circle' : 'bi-check2-circle' }}"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">Tidak ada data denda yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-foot">
            <span class="info">
                @if ($rows->total())
                    Menampilkan {{ number_format($rows->firstItem(), 0, ',', '.') }} - {{ number_format($rows->lastItem(), 0, ',', '.') }}
                    dari {{ number_format($rows->total(), 0, ',', '.') }} data
                @else
                    Menampilkan 0 data
                @endif
            </span>
            {{ $rows->links('partials.pagination') }}
        </div>
    </div>
@endsection

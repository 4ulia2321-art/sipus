@extends('layouts.master')

@section('title', 'Kategori Buku')
@section('role', 'admin')
@section('search_placeholder', 'Cari kategori buku...')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota.css') }}">
@endpush

@section('content')
    {{-- Header halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-tags-fill"></i>
            <div>
                <h2>Kategori Buku</h2>
                <p>Kelola kategori koleksi perpustakaan SMPN 01 Pasirjambu.</p>
            </div>
        </div>
        <a href="{{ route('admin.kategori.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Kategori
        </a>
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
                ['icon' => 'bi-tags-fill',        'color' => 'stat-blue',   'label' => 'Total Kategori', 'value' => $stat['total'],      'sub' => 'Kategori terdaftar'],
                ['icon' => 'bi-check-circle-fill', 'color' => 'stat-green',  'label' => 'Digunakan',      'value' => $stat['terpakai'],   'sub' => 'Memiliki koleksi'],
                ['icon' => 'bi-inbox-fill',       'color' => 'stat-orange', 'label' => 'Tanpa Buku',     'value' => $stat['tanpa_buku'], 'sub' => 'Belum berisi buku'],
            ];
        @endphp
        @foreach ($cards as $s)
            <div class="stat-card {{ $s['color'] }}">
                <div class="stat-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                <h4>{{ $s['label'] }}</h4>
                <div class="stat-value">{{ number_format($s['value'], 0, ',', '.') }}</div>
                <div class="stat-delta"><i class="bi bi-book"></i> {{ $s['sub'] }}</div>
            </div>
        @endforeach
    </section>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.kategori') }}" class="filter-bar" data-live-form>
        <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama kategori atau keterangan...">
        </div>
        <a href="{{ route('admin.kategori') }}" class="btn btn-outline"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>

    {{-- Tabel --}}
    <div class="card table-card" data-live-part="list">
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Kategori</th>
                        <th>Keterangan</th>
                        <th>Jumlah Buku</th>
                        <th class="t-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kategori as $r)
                        <tr>
                            <td>{{ $kategori->firstItem() + $loop->index }}</td>
                            <td class="judul">{{ $r->nama_kategori }}</td>
                            <td class="muted">{{ $r->keterangan ?: '-' }}</td>
                            <td><span class="pill blue">{{ $r->jumlah_buku }} buku</span></td>
                            <td>
                                <div class="actions center">
                                    <a href="{{ route('admin.kategori.edit', $r->id_kategori) }}" class="act edit" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('admin.kategori.destroy', $r->id_kategori) }}" method="POST"
                                          onsubmit="return confirm('Hapus kategori &quot;{{ $r->nama_kategori }}&quot;?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act del" title="Hapus" @disabled($r->jumlah_buku > 0)>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Belum ada data kategori.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-foot">
            <span class="info">
                @if ($kategori->total())
                    Menampilkan {{ number_format($kategori->firstItem(), 0, ',', '.') }} - {{ number_format($kategori->lastItem(), 0, ',', '.') }}
                    dari {{ number_format($kategori->total(), 0, ',', '.') }} kategori
                @else
                    Menampilkan 0 kategori
                @endif
            </span>
            {{ $kategori->links('partials.pagination') }}
        </div>
    </div>
@endsection

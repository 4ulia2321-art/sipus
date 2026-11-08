@extends('layouts.master')

@section('title', 'Kelola Kelas')
@section('role', 'admin')
@section('search_placeholder', 'Cari nama kelas atau wali kelas...')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota.css') }}">
@endpush

@section('content')
    {{-- Header halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-diagram-3-fill"></i>
            <div>
                <h2>Kelola Kelas</h2>
                <p>Kelola daftar kelas dan wali kelas perpustakaan SMPN 01 Pasirjambu.</p>
            </div>
        </div>
        <a href="{{ route('admin.kelas.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Kelas
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
                ['icon' => 'bi-diagram-3-fill', 'color' => 'stat-blue',   'label' => 'Total Kelas', 'value' => $stat['total'],    'sub' => 'Seluruh tingkat'],
                ['icon' => 'bi-1-circle-fill',  'color' => 'stat-green',  'label' => 'Kelas VII',   'value' => $stat['tingkat7'], 'sub' => 'Tingkat VII'],
                ['icon' => 'bi-2-circle-fill',  'color' => 'stat-orange', 'label' => 'Kelas VIII',  'value' => $stat['tingkat8'], 'sub' => 'Tingkat VIII'],
                ['icon' => 'bi-3-circle-fill',  'color' => 'stat-purple', 'label' => 'Kelas IX',    'value' => $stat['tingkat9'], 'sub' => 'Tingkat IX'],
            ];
        @endphp
        @foreach ($cards as $s)
            <div class="stat-card {{ $s['color'] }}">
                <div class="stat-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                <h4>{{ $s['label'] }}</h4>
                <div class="stat-value">{{ number_format($s['value'], 0, ',', '.') }}</div>
                <div class="stat-delta"><i class="bi bi-mortarboard"></i> {{ $s['sub'] }}</div>
            </div>
        @endforeach
    </section>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.kelas') }}" class="filter-bar" data-live-form>
        <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama kelas atau wali kelas...">
        </div>

        <select name="tingkat" onchange="this.form.submit()">
            <option value="">Semua Tingkat</option>
            @foreach ($daftarTingkat as $t)
                <option value="{{ $t }}" @selected(request('tingkat') === $t)>{{ $t }}</option>
            @endforeach
        </select>

        <a href="{{ route('admin.kelas') }}" class="btn btn-outline"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>

    {{-- Tabel --}}
    <div class="card table-card" data-live-part="list">
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Kelas</th>
                        <th>Tingkat</th>
                        <th>Wali Kelas</th>
                        <th>Jumlah Siswa</th>
                        <th class="t-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="judul">{{ $r->nama_kelas }}</td>
                            <td><span class="pill blue">{{ $r->tingkat ?? '-' }}</span></td>
                            <td class="muted">{{ $r->wali_kelas ?? '-' }}</td>
                            <td>{{ $r->jumlah_siswa }} siswa</td>
                            <td>
                                <div class="actions center">
                                    <a href="{{ route('admin.kelas.edit', $r->id) }}" class="act edit" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('admin.kelas.destroy', $r->id) }}" method="POST"
                                          onsubmit="return confirm('Hapus kelas &quot;{{ $r->nama_kelas }}&quot;?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act del" title="Hapus" @disabled(! $r->bisa_hapus)>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Belum ada data kelas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-foot">
            <span class="info">
                @if ($rows->total())
                    Menampilkan {{ number_format($rows->firstItem(), 0, ',', '.') }} - {{ number_format($rows->lastItem(), 0, ',', '.') }}
                    dari {{ number_format($rows->total(), 0, ',', '.') }} kelas
                @else
                    Menampilkan 0 kelas
                @endif
            </span>
            {{ $rows->links('partials.pagination') }}
        </div>
    </div>
@endsection

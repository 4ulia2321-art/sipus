@extends('layouts.master')

@section('title', 'Kategori ' . $namaKategori)

@push('styles')
    <style>
        .catalog-section {
            margin-bottom: 30px;
        }

        .catalog-section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .catalog-section-head h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
        }

        .catalog-section-head span {
            font-size: .82rem;
            color: var(--muted);
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    <div class="greeting">
        <h1>Kategori: {{ $namaKategori }}</h1>
        <p>Daftar buku dalam kategori ini.</p>
    </div>

    <a href="{{ route('katalog') }}" class="btn-outline" style="margin-bottom:16px;">
        <i class="bi bi-arrow-left"></i> Semua Kategori
    </a>

    {{-- Form pencarian & filter --}}
   <form action="{{ route('katalog.kategori', ['nama' => $namaKategori]) }}" method="GET" class="catalog-filter" data-live-form>
        <div class="filter-search-row">
            <div class="filter-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari judul atau pengarang...">
                @if (request('q'))
                   <a href="{{ route('katalog.kategori', ['nama' => $namaKategori]) }}" class="clear-search" title="Hapus pencarian">
                       <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
            <button type="submit" class="btn-search">Cari</button>
        </div>

        <div class="filter-select-row">
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="tersedia" @selected(request('status') === 'tersedia')>Tersedia</option>
                <option value="dipinjam" @selected(request('status') === 'dipinjam')>Dipinjam</option>
            </select>

            <select name="urutan" onchange="this.form.submit()">
                <option value="terbaru" @selected(request('urutan', 'terbaru') === 'terbaru')>Urutkan: Terbaru</option>
                <option value="judul" @selected(request('urutan') === 'judul')>Urutkan: Judul A-Z</option>
                <option value="pengarang" @selected(request('urutan') === 'pengarang')>Urutkan: Pengarang A-Z</option>
            </select>
        </div>
    </form>

    <div data-live-part="list">
        @if (isset($bukuPerKelas))
            @forelse ($bukuPerKelas as $kelas => $bukuKelas)
                <section class="catalog-section">
                    <div class="catalog-section-head">
                        <h2>{{ $kelas === 'Tanpa Kelas' ? 'Tanpa Kelas' : 'Kelas ' . $kelas }}</h2>
                        <span>{{ $bukuKelas->count() }} buku</span>
                    </div>

                    <div class="catalog-grid">
                        @foreach ($bukuKelas as $buku)
                            @include('katalog.partials.buku-card', ['buku' => $buku])
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="empty">Buku tidak ditemukan.</p>
            @endforelse
        @elseif ($bukuList->isEmpty())
            <p class="empty">Buku tidak ditemukan.</p>
        @else
            <div class="catalog-grid">
                @foreach ($bukuList as $buku)
                    @include('katalog.partials.buku-card', ['buku' => $buku])
                @endforeach
            </div>
        @endif
    </div>

    @unless (isset($bukuPerKelas))
        {{-- Pagination --}}
        <div data-live-part="pagination">
            @if ($bukuList->hasPages())
                {{ $bukuList->links('partials.pagination') }}
            @endif
        </div>
    @endunless
@endsection

@extends('layouts.master')

@section('title', 'Katalog Buku')
@section('navbar_links', true)

@section('content')
    <div class="greeting">
        <h1>Katalog Buku</h1>
        <p>Temukan buku favorit mu, dan mulai membaca sekarang!</p>
    </div>

    {{-- Form pencarian & filter --}}
    <form action="{{ route('katalog') }}" method="GET" class="catalog-filter" data-live-form>
        <div class="filter-search-row">
            <div class="filter-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari judul buku, pengarang, atau kategori...">
                @if (request('q'))
                    <a href="{{ route('katalog', request()->except('q', 'page')) }}" class="clear-search" title="Hapus pencarian">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
            <button type="submit" class="btn-search">Cari</button>
        </div>

        <div class="filter-select-row">
            <select name="kategori" onchange="this.form.submit()">
                <option value="">Semua kategori</option>
                @foreach ($kategoriList as $kategori)
                    <option value="{{ $kategori }}" @selected(request('kategori') === $kategori)>{{ $kategori }}</option>
                @endforeach
            </select>

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

    {{-- Grid buku --}}
    <section class="catalog-grid" data-live-part="list">
        @forelse ($bukuList as $buku)
            <article class="catalog-card">
                <div class="catalog-cover">
                    @if ($buku->cover)
                        <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}">
                    @else
                        <i class="bi bi-image"></i>
                    @endif
                </div>
                <h4>{{ $buku->judul }}</h4>
                <small>{{ $buku->pengarang }}</small>
                <span class="badge {{ $buku->status === 'tersedia' ? 'badge-ok' : 'badge-out' }}">
                    {{ ucfirst($buku->status) }}
                </span>
                <a href="{{ route('buku.show', $buku->id) }}" class="btn-detail">Lihat Detail</a>
            </article>
        @empty
            <p class="empty catalog-empty">Buku tidak ditemukan.</p>
        @endforelse
    </section>

    {{-- Pagination --}}
    <div data-live-part="pagination">
        @if ($bukuList->hasPages())
            {{ $bukuList->links('partials.pagination') }}
        @endif
    </div>
@endsection

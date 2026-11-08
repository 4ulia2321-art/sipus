@extends('layouts.master')

@section('title', 'Katalog Buku')

@push('styles')
    <style>
        .kategori-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .kategori-card {
            display: flex;
            flex-direction: column;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            color: inherit;
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .kategori-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(28, 68, 124, .14);
        }

        .kategori-cover {
            aspect-ratio: 16 / 9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: linear-gradient(135deg, #e8f0fb, #dbe7f8);
            color: var(--brand);
            font-size: 42px;
        }

        .kategori-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kategori-body {
            padding: 16px;
        }

        .kategori-body h3 {
            margin: 0 0 4px;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
        }

        .kategori-body span {
            font-size: .82rem;
            color: var(--muted);
        }

        .kategori-open {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            font-size: .85rem;
            font-weight: 700;
            color: var(--brand);
        }
    </style>
@endpush

@section('content')
    <div class="greeting">
        <h1>Katalog Buku</h1>
        <p>Pilih kategori untuk melihat daftar bukunya.</p>
    </div>

    @if ($kategoriCards->isEmpty())
        <p class="empty">Belum ada kategori buku.</p>
    @else
        <div class="kategori-grid">
            @foreach ($kategoriCards as $kategori)
              <a href="{{ route('katalog.kategori', ['nama' => $kategori->nama_kategori]) }}" class="kategori-card">
                    <div class="kategori-cover">
                        @if ($kategori->cover)
                            <img src="{{ asset('storage/' . $kategori->cover) }}" alt="{{ $kategori->nama_kategori }}">
                        @else
                            <i class="bi bi-book"></i>
                        @endif
                    </div>
                    <div class="kategori-body">
                       <h3>{{ $kategori->nama_kategori }}</h3>
                        <span>{{ $kategori->jumlah }} buku</span>
                        <div class="kategori-open">Lihat Buku <i class="bi bi-arrow-right"></i></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection

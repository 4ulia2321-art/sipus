<article class="book-card">
    <div class="book-cover">
        @if (! empty($buku->cover))
            <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}" loading="lazy">
        @else
            <i class="bi bi-book"></i>
        @endif
    </div>
    <div class="book-body">
        <h4>{{ $buku->judul }}</h4>
        <small>{{ $buku->pengarang }}</small>
        <span class="badge {{ $buku->status === 'tersedia' ? 'badge-ok' : 'badge-out' }}">
            {{ ucfirst($buku->status) }}
        </span>
        <a href="{{ route('buku.show', $buku->id_buku) }}" class="btn-detail">Lihat Detail</a>
    </div>
</article>

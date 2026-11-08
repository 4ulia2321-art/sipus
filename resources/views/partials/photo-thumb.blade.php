<span class="photo-thumb">
    @if (!empty($foto))
        <img src="{{ asset('storage/' . $foto) }}" alt="{{ $alt ?? 'Foto' }}">
    @else
        <i class="bi bi-person"></i>
    @endif
</span>

<span class="avatar">
    @if (!empty($foto))
        <img src="{{ asset('storage/' . $foto) }}" alt="Foto">
    @else
        <i class="bi bi-person"></i>
    @endif
</span>
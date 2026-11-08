{{-- Tombol aksi (lihat, edit, hapus). Dipakai tabel desktop dan kartu mobile. --}}
<div class="actions {{ ($small ?? false) ? 'small' : '' }}">
    <a href="{{ route('pustakawan.anggota.show', $a['id']) }}" class="act view" title="Lihat"><i class="bi bi-eye-fill"></i></a>
    <a href="{{ route('pustakawan.anggota.edit', $a['id']) }}" class="act edit" title="Edit"><i class="bi bi-pencil-fill"></i></a>
    <form action="{{ route('pustakawan.anggota.destroy', $a['id']) }}" method="POST"
          onsubmit="return confirm('Hapus anggota &quot;{{ $a['nama'] }}&quot;?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="act del" title="Hapus"><i class="bi bi-trash-fill"></i></button>
    </form>
</div>

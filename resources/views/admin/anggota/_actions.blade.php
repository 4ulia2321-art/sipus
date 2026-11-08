<div class="icon-actions">
    <a href="{{ route($area.'.anggota.show', $row->id) }}" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
    <a href="{{ route($area.'.anggota.edit', $row->id) }}" class="btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
    <form action="{{ route($area.'.anggota.destroy', $row->id) }}" method="POST"
          onsubmit="return confirm('Hapus anggota {{ $row->nama }}?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-icon btn-icon-danger" title="Hapus"><i class="bi bi-trash"></i></button>
    </form>
</div>

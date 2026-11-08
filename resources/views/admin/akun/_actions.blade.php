<div class="icon-actions">
    @if (($row->reset_status ?? 'none') === 'pending')
        <form action="{{ route('admin.akun.reset', $row->id) }}" method="POST"
              onsubmit="return confirm('Konfirmasi reset password untuk {{ $row->username }}?\nPengguna akan diminta membuat password baru saat login.')">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn-icon btn-icon-warning" title="Konfirmasi reset password"><i class="bi bi-key-fill"></i></button>
        </form>
    @endif
    <button type="button" class="btn-icon" data-buka-kaitkan
            data-id="{{ $row->id }}"
            data-username="{{ $row->username }}"
            data-siswa="{{ $row->id_siswa }}"
            data-nama="{{ $row->nama_siswa }}"
            title="{{ $row->id_siswa ? 'Ganti kaitan anggota' : 'Kaitkan dengan anggota' }}">
        <i class="bi {{ $row->id_siswa ? 'bi-arrow-left-right' : 'bi-link-45deg' }}"></i>
    </button>
    <a href="{{ route('admin.akun.edit', $row->id) }}" class="btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
    <form action="{{ route('admin.akun.destroy', $row->id) }}" method="POST"
          onsubmit="return confirm('Hapus akun {{ $row->username }}?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-icon btn-icon-danger" title="Hapus"><i class="bi bi-trash"></i></button>
    </form>
</div>

@extends('layouts.master')

@section('title', 'Tambah Kategori Buku')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-tags"></i>
            <div>
                <h1>Tambah Kategori Buku</h1>
                <p>Tambahkan kategori baru untuk koleksi perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route('admin.kategori') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.kategori.store') }}" method="POST" class="form-card">
        @csrf

        <div class="form-group">
            <label>Nama Kategori</label>
            <input type="text" name="nama_kategori" value="{{ old('nama_kategori') }}" placeholder="Contoh: Pelajaran" required>
        </div>

        <div class="form-group">
            <label>Keterangan <small>opsional</small></label>
            <textarea name="keterangan" rows="3" placeholder="Deskripsi singkat kategori">{{ old('keterangan') }}</textarea>
        </div>

        <div class="loan-actions">
            <a href="{{ route('admin.kategori') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Kategori
            </button>
        </div>
    </form>
@endsection

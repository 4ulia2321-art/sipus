@extends('layouts.master')

@section('title', 'Edit Kategori Buku')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-tags"></i>
            <div>
                <h1>Edit Kategori Buku</h1>
                <p>Perbarui data kategori {{ $kategori->nama_kategori }}.</p>
            </div>
        </div>
        <a href="{{ route('admin.kategori') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.kategori.update', $kategori->id_kategori) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Nama Kategori</label>
            <input type="text" name="nama_kategori" value="{{ old('nama_kategori', $kategori->nama_kategori) }}" required>
        </div>

        <div class="form-group">
            <label>Keterangan <small>opsional</small></label>
            <textarea name="keterangan" rows="3">{{ old('keterangan', $kategori->keterangan) }}</textarea>
        </div>

        <div class="loan-actions">
            <a href="{{ route('admin.kategori') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

@extends('layouts.master')

@section('title', 'Tambah Buku')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-journal-plus"></i>
            <div>
                <h1>Tambah Buku</h1>
                <p>Tambahkan koleksi buku baru ke perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route('admin.buku') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.buku.store') }}" method="POST" class="form-card">
        @csrf

        <div class="loan-grid">
            <div class="form-group">
                <label>No Seri Buku</label>
                <input type="text" name="no_seri_buku" value="{{ old('no_seri_buku') }}" required>
            </div>
            <div class="form-group">
                <label>Kategori</label>
                <select name="id_kategori" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($kategori as $k)
                        <option value="{{ $k->id_kategori }}" @selected(old('id_kategori') == $k->id_kategori)>{{ $k->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Judul Buku</label>
            <input type="text" name="judul" value="{{ old('judul') }}" required>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Pengarang <small>opsional</small></label>
                <input type="text" name="pengarang" value="{{ old('pengarang') }}">
            </div>
            <div class="form-group">
                <label>Penerbit <small>opsional</small></label>
                <input type="text" name="penerbit" value="{{ old('penerbit') }}">
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Tahun Terbit <small>opsional</small></label>
                <input type="number" name="tahun_terbit" value="{{ old('tahun_terbit') }}" min="1900" max="2100">
            </div>
            <div class="form-group">
                <label>Stok</label>
                <input type="number" name="stok" value="{{ old('stok', 1) }}" min="0" required>
            </div>
        </div>

        <div class="form-group">
            <label>Lokasi Rak <small>opsional</small></label>
            <input type="text" name="lokasi_rak" value="{{ old('lokasi_rak') }}">
        </div>

        <div class="loan-actions">
            <a href="{{ route('admin.buku') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Buku
            </button>
        </div>
    </form>
@endsection

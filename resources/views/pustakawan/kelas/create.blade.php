@extends('layouts.master')

@section('title', 'Tambah Kelas')
@section('role', 'pustakawan')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-diagram-3"></i>
            <div>
                <h1>Tambah Kelas</h1>
                <p>Tambahkan data kelas baru ke perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route('pustakawan.kelas') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('pustakawan.kelas.store') }}" method="POST" class="form-card">
        @csrf

        <div class="loan-grid">
            <div class="form-group">
                <label>Nama Kelas</label>
                <input type="text" name="nama_kelas" value="{{ old('nama_kelas') }}" placeholder="Contoh: VII A" required>
            </div>
            <div class="form-group">
                <label>Tingkat</label>
                <input type="text" name="tingkat" value="{{ old('tingkat') }}" placeholder="Contoh: VII">
            </div>
        </div>

        <div class="form-group">
            <label>Wali Kelas <small>opsional</small></label>
            <input type="text" name="wali_kelas" value="{{ old('wali_kelas') }}">
        </div>

        <div class="loan-actions">
            <a href="{{ route('pustakawan.kelas') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Kelas
            </button>
        </div>
    </form>
@endsection

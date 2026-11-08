@extends('layouts.master')

@section('title', 'Edit Kelas')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-diagram-3"></i>
            <div>
                <h1>Edit Kelas</h1>
                <p>Perbarui data kelas {{ $kelas->nama_kelas }}.</p>
            </div>
        </div>
        <a href="{{ route('admin.kelas') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.kelas.update', $kelas->id_kelas) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Nama Kelas</label>
                <input type="text" name="nama_kelas" value="{{ old('nama_kelas', $kelas->nama_kelas) }}" required>
            </div>
            <div class="form-group">
                <label>Tingkat</label>
                <input type="text" name="tingkat" value="{{ old('tingkat', $kelas->tingkat) }}" placeholder="Contoh: VII">
            </div>
        </div>

        <div class="form-group">
            <label>Wali Kelas <small>opsional</small></label>
            <input type="text" name="wali_kelas" value="{{ old('wali_kelas', $kelas->wali_kelas) }}">
        </div>

        <div class="loan-actions">
            <a href="{{ route('admin.kelas') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

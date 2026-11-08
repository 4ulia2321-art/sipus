@extends('layouts.master')

@section('title', 'Pengaturan Sistem')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-gear-fill"></i>
            <div>
                <h1>Pengaturan Sistem</h1>
                <p>Kelola identitas dan informasi layanan perpustakaan.</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.pengaturan.update') }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Nama Perpustakaan</label>
                <input type="text" name="nama_perpustakaan" value="{{ old('nama_perpustakaan', $nilai['nama_perpustakaan'] ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Nomor Telepon</label>
                <input type="text" name="no_telp" value="{{ old('no_telp', $nilai['no_telp'] ?? '') }}">
            </div>
        </div>

        <div class="form-group">
            <label>Alamat</label>
            <input type="text" name="alamat" value="{{ old('alamat', $nilai['alamat'] ?? '') }}">
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Jam Operasional</label>
                <input type="text" name="jam_operasional" value="{{ old('jam_operasional', $nilai['jam_operasional'] ?? '') }}" placeholder="Contoh: Senin - Jumat, 07.00 - 15.00 WIB">
            </div>
            <div class="form-group">
                <label>Kepala Perpustakaan</label>
                <input type="text" name="kepala_perpustakaan" value="{{ old('kepala_perpustakaan', $nilai['kepala_perpustakaan'] ?? '') }}">
            </div>
        </div>

        <div class="loan-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
@endsection

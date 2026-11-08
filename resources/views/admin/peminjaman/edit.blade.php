@extends('layouts.master')

@section('title', 'Edit Peminjaman')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-pencil-square"></i>
            <div>
                <h1>Edit Peminjaman</h1>
                <p>Perbarui tanggal dan status peminjaman.</p>
            </div>
        </div>
        <a href="{{ route($area.'.peminjaman') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route($area.'.peminjaman.update', $peminjaman->id_peminjaman) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Tanggal Pinjam</label>
                <input type="date" name="tanggal_pinjam"
                       value="{{ old('tanggal_pinjam', \Carbon\Carbon::parse($peminjaman->tanggal_pinjam)->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Tanggal Jatuh Tempo</label>
                <input type="date" name="tanggal_jatuh_tempo"
                       value="{{ old('tanggal_jatuh_tempo', \Carbon\Carbon::parse($peminjaman->tanggal_jatuh_tempo)->format('Y-m-d')) }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status" required>
                @foreach (['Menunggu', 'Dipinjam', 'Ditolak'] as $s)
                    <option value="{{ $s }}" @selected(old('status', $peminjaman->status) === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>

        <div class="loan-actions">
            <a href="{{ route($area.'.peminjaman') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection
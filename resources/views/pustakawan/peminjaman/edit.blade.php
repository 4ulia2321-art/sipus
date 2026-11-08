@extends('layouts.master')

@section('title', ($asal ?? null) === 'keterlambatan' ? 'Edit Keterlambatan' : 'Edit Peminjaman')
@section('role', 'pustakawan')

@php
    $dariKeterlambatan = ($asal ?? null) === 'keterlambatan';
    $kembaliUrl = $dariKeterlambatan ? route('pustakawan.keterlambatan') : route('pustakawan.peminjaman');
@endphp

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-pencil-square"></i>
            <div>
                <h1>{{ $dariKeterlambatan ? 'Edit Keterlambatan' : 'Edit Peminjaman' }}</h1>
                <p>{{ $dariKeterlambatan ? 'Perbarui status peminjaman yang melewati jatuh tempo.' : 'Perbarui tanggal dan status peminjaman.' }}</p>
            </div>
        </div>
        <a href="{{ $kembaliUrl }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    @if ($dariKeterlambatan)
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Bila buku sudah dikembalikan, catat lewat menu
            <a href="{{ route('pustakawan.pengembalian.create') }}">Pengembalian</a>
            agar stok buku dan denda ikut diperbarui.
        </div>
    @endif

    <form action="{{ route('pustakawan.peminjaman.update', $peminjaman->id_peminjaman) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        @if ($dariKeterlambatan)
            <input type="hidden" name="asal" value="keterlambatan">
        @endif

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
            <a href="{{ $kembaliUrl }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

@extends('layouts.master')

@section('title', 'Ketentuan Denda')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-cash-coin"></i>
            <div>
                <h1>Ketentuan Denda</h1>
                <p>Aturan ini dipakai sistem saat menghitung denda keterlambatan pengembalian buku.</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.ketentuan-denda.update') }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Denda per Hari (Rp)</label>
                <input type="number" name="denda_per_hari" value="{{ old('denda_per_hari', $nilai['denda_per_hari'] ?? 500) }}" min="0" step="100" required>
                <small class="muted">Nominal yang dikalikan jumlah hari keterlambatan.</small>
            </div>
            <div class="form-group">
                <label>Lama Peminjaman (hari)</label>
                <input type="number" name="lama_pinjam_hari" value="{{ old('lama_pinjam_hari', $nilai['lama_pinjam_hari'] ?? 7) }}" min="1" max="365" required>
                <small class="muted">Masa pinjam default saat pengajuan atau perpanjangan.</small>
            </div>
        </div>

        <div class="loan-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Ketentuan
            </button>
        </div>
    </form>
@endsection

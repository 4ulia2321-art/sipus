@extends('layouts.master')

@section('title', 'Edit Denda')
@section('role', 'admin')

@section('content')
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-cash-coin"></i>
            <div>
                <h1>Edit Denda</h1>
                <p>Sesuaikan nominal dan status pembayaran denda.</p>
            </div>
        </div>
        <a href="{{ route($area.'.denda') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route($area.'.denda.update', $denda->id_pengembalian) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Nama Anggota</label>
                <input type="text" value="{{ $denda->nama_siswa }}" readonly>
            </div>
            <div class="form-group">
                <label>Jatuh Tempo</label>
                <input type="text" value="{{ $denda->tanggal_jatuh_tempo ? \Carbon\Carbon::parse($denda->tanggal_jatuh_tempo)->translatedFormat('d M Y') : '-' }}" readonly>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Tanggal Kembali</label>
                <input type="text" value="{{ $denda->tanggal_kembali ? \Carbon\Carbon::parse($denda->tanggal_kembali)->translatedFormat('d M Y') : '-' }}" readonly>
            </div>
            <div class="form-group">
                <label>Nominal Denda (Rp)</label>
                <input type="number" name="denda" value="{{ old('denda', (int) $denda->denda) }}" min="0" step="500" required>
            </div>
        </div>

        <div class="form-group">
            <label>Status Pembayaran</label>
            <select name="status_bayar" required>
                @foreach (['Belum Lunas', 'Lunas'] as $st)
                    <option value="{{ $st }}" @selected(old('status_bayar', $denda->status_bayar) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>

        <div class="loan-actions">
            <a href="{{ route($area.'.denda') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Denda
            </button>
        </div>
    </form>
@endsection

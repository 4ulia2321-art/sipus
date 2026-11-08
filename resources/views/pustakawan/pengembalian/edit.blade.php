@extends('layouts.master')

@section('title', 'Edit Pengembalian')
@section('role', 'pustakawan')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-pencil-square"></i>
            <div>
                <h1>Edit Pengembalian</h1>
                <p>Perbaiki data keterlambatan pengembalian yang sudah diselesaikan.</p>
            </div>
        </div>
        <a href="{{ route('pustakawan.pengembalian.show', $pengembalian->id_pengembalian) }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('pustakawan.pengembalian.update', $pengembalian->id_pengembalian) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Nama Anggota</label>
                <input type="text" value="{{ $pengembalian->nama_siswa }} ({{ $pengembalian->nis }})" readonly>
            </div>
            <div class="form-group">
                <label>Kelas</label>
                <input type="text" value="{{ $pengembalian->kelas }}" readonly>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Judul Buku</label>
                <input type="text" value="{{ $pengembalian->judul }}" readonly>
            </div>
            <div class="form-group">
                <label>Pengarang</label>
                <input type="text" value="{{ $pengembalian->pengarang ?? '-' }}" readonly>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Tanggal Pinjam</label>
                <input type="text" value="{{ $pengembalian->tanggal_pinjam->translatedFormat('d M Y') }}" readonly>
            </div>
            <div class="form-group">
                <label>Jatuh Tempo</label>
                <input type="text" value="{{ $pengembalian->tanggal_jatuh_tempo->translatedFormat('d M Y') }}" readonly>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Tanggal Kembali</label>
                <input type="date" name="tanggal_kembali"
                       value="{{ old('tanggal_kembali', $pengembalian->tanggal_kembali->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Status Keterlambatan</label>
                <select name="kategori" required>
                    @foreach (['Tepat Waktu', 'Terlambat'] as $status)
                        <option value="{{ $status }}"
                            @selected(old('kategori', $pengembalian->kategori ?: ($pengembalian->lama_terlambat > 0 ? 'Terlambat' : 'Tepat Waktu')) === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
                <small class="muted">Pilih "Tepat Waktu" bila keterlambatan sudah diselesaikan.</small>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Denda (Rp)</label>
                <input type="number" name="denda" min="0" step="500"
                       value="{{ old('denda', (int) $pengembalian->denda) }}" required>
            </div>
            <div class="form-group">
                <label>Status Pembayaran Denda</label>
                <select name="status_bayar" required>
                    @foreach (['Belum Lunas', 'Lunas'] as $status)
                        <option value="{{ $status }}" @selected(old('status_bayar', $pengembalian->status_bayar ?: 'Belum Lunas') === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Lama Terlambat Saat Ini</label>
            <input type="text" value="{{ $pengembalian->lama_terlambat > 0 ? $pengembalian->lama_terlambat . ' hari' : 'Tidak terlambat' }}" readonly>
        </div>

        <div class="loan-actions">
            <a href="{{ route('pustakawan.pengembalian.show', $pengembalian->id_pengembalian) }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

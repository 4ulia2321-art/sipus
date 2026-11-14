@extends('layouts.master')

@section('title', 'Input Pengembalian')
@section('role', 'pustakawan')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-arrow-repeat"></i>
            <div>
                <h1>Input Pengembalian Manual</h1>
                <p>Catat pengembalian buku dari anggota perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route('pustakawan.pengembalian') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    @if ($peminjamanAktif->isEmpty())
        <div class="alert alert-info">
            Tidak ada peminjaman aktif yang menunggu pengembalian.
        </div>
    @endif

    <form action="{{ route('pustakawan.pengembalian.store') }}" method="POST" class="form-card">
        @csrf

        <div class="form-group">
            <label>Peminjaman</label>
            <select name="id_peminjaman" required>
                <option value="">-- Pilih peminjaman --</option>
                @foreach ($peminjamanAktif as $p)
                    <option value="{{ $p->id_peminjaman }}" @selected(old('id_peminjaman') == $p->id_peminjaman)>
                        {{ $p->nama_siswa }} ({{ $p->nis }}) &mdash; {{ $p->judul }}
                        &middot; tempo {{ \Carbon\Carbon::parse($p->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}
                        @if ($p->pengajuan_kembali_at)
                            &middot; pengajuan pengembalian
                        @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Tanggal Kembali</label>
                <input type="date" name="tanggal_kembali" value="{{ old('tanggal_kembali', today()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Denda (Rp) <small>opsional</small></label>
                <input type="number" name="denda" min="0" step="500" value="{{ old('denda') }}" placeholder="Otomatis bila dikosongkan">
            </div>
        </div>

        <div class="loan-actions">
            <a href="{{ route('pustakawan.pengembalian') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary" @disabled($peminjamanAktif->isEmpty())>
                <i class="bi bi-save"></i> Simpan Pengembalian
            </button>
        </div>
    </form>
@endsection

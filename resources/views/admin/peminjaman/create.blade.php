@extends('layouts.master')

@section('title', 'Peminjaman Baru')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-file-earmark-plus"></i>
            <div>
                <h1>Peminjaman Baru</h1>
                <p>Catat peminjaman buku untuk anggota perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route($area.'.peminjaman') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    @if ($anggota->isEmpty() || $bukuList->isEmpty())
        <div class="alert alert-info">
            @if ($anggota->isEmpty())
                Belum ada data anggota.
            @endif
            @if ($bukuList->isEmpty())
                Belum ada buku dengan stok tersedia.
            @endif
            Lengkapi data terlebih dahulu sebelum mencatat peminjaman.
        </div>
    @endif

    <form action="{{ route($area.'.peminjaman.store') }}" method="POST" class="form-card">
        @csrf

        <div class="form-group">
            <label>Anggota</label>
            <select name="id_siswa" required>
                <option value="">-- Pilih anggota --</option>
                @foreach ($anggota as $a)
                    <option value="{{ $a->id_siswa }}" @selected(old('id_siswa') == $a->id_siswa)>
                        {{ $a->nama_siswa }} ({{ $a->nis }} &middot; {{ $a->kelas }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Buku</label>
            <select name="id_buku" required>
                <option value="">-- Pilih buku --</option>
                @foreach ($bukuList as $b)
                    <option value="{{ $b->id_buku }}" @selected(old('id_buku') == $b->id_buku)>
                        {{ $b->judul }} &mdash; {{ $b->pengarang ?? '-' }} (stok: {{ $b->stok }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Tanggal Pinjam</label>
                <input type="date" name="tanggal_pinjam" value="{{ old('tanggal_pinjam', today()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Tanggal Jatuh Tempo</label>
                <input type="date" name="tanggal_jatuh_tempo" value="{{ old('tanggal_jatuh_tempo', today()->addDays(7)->format('Y-m-d')) }}" required>
            </div>
        </div>

        <div class="loan-actions">
            <a href="{{ route($area.'.peminjaman') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary" @disabled($anggota->isEmpty() || $bukuList->isEmpty())>
                <i class="bi bi-save"></i> Simpan Peminjaman
            </button>
        </div>
    </form>
@endsection
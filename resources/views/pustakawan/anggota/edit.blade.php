@extends('layouts.master')

@section('title', 'Edit Anggota')
@section('role', 'pustakawan')

@push('styles')
    {{-- buku.css: komponen bersama (banner pendukung, tombol, form) --}}
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota-pustakawan.css') }}">
@endpush

@section('content')
    @php
        $sekarang = \Carbon\Carbon::now()->locale('id');
    @endphp

    {{-- Banner judul --}}
    <section class="page-banner">
        <span class="banner-icon"><i class="bi bi-pencil-square"></i></span>
        <div class="banner-text">
            <h2>Edit Anggota</h2>
            <p class="hide-mobile">Perbarui data anggota perpustakaan.</p>
            <p class="show-mobile">Perbarui data anggota.</p>
        </div>
        <div class="date-chip hide-mobile">
            <i class="bi bi-calendar3"></i>
            <div>
                <span>{{ $sekarang->translatedFormat('l, j F Y') }}</span>
                <span>{{ $sekarang->format('H:i') }} WIB</span>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('pustakawan.anggota.update', $anggota->id_siswa) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Username / Email</label>
                <input type="text" name="username" value="{{ old('username', $anggota->username) }}" required>
            </div>
            <div class="form-group">
                <label>Password <small>kosongkan bila tidak diubah</small></label>
                <input type="password" name="password">
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>NIS</label>
                <input type="text" name="nis" value="{{ old('nis', $anggota->nis) }}" required>
            </div>
            <div class="form-group">
                <label>Nama Anggota</label>
                <input type="text" name="nama_siswa" value="{{ old('nama_siswa', $anggota->nama_siswa) }}" required>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Kelas</label>
                <select name="kelas" required>
                    <option value="">Pilih Kelas</option>
                    @foreach ($daftarKelas as $k)
                        <option value="{{ $k }}" @selected(old('kelas', $anggota->kelas) === $k)>{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>No. Telepon <small>opsional</small></label>
                <input type="text" name="no_telp" value="{{ old('no_telp', $anggota->no_telp) }}">
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin">
                    <option value="">Pilih jenis kelamin</option>
                    <option value="Laki-laki" @selected(old('jenis_kelamin', $anggota->jenis_kelamin) === 'Laki-laki')>Laki-laki</option>
                    <option value="Perempuan" @selected(old('jenis_kelamin', $anggota->jenis_kelamin) === 'Perempuan')>Perempuan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $anggota->tempat_lahir) }}">
            </div>
        </div>

        <div class="form-group">
            <label>Tanggal Lahir</label>
            <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $anggota->tanggal_lahir) }}">
        </div>

        <div class="form-group">
            <label>Alamat <small>opsional</small></label>
            <textarea name="alamat" rows="3">{{ old('alamat', $anggota->alamat) }}</textarea>
        </div>

        <div class="loan-actions">
            <a href="{{ route('pustakawan.anggota') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

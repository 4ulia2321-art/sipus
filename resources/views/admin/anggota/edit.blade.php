@extends('layouts.master')

@section('title', 'Edit Anggota')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-pencil-square"></i>
            <div>
                <h1>Edit Anggota</h1>
                <p>Perbarui data anggota perpustakaan.</p>
            </div>
        </div>
        <a href="{{ route($area.'.anggota') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route($area.'.anggota.update', $anggota->id_siswa) }}" method="POST" class="form-card">
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
            <a href="{{ route($area.'.anggota') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

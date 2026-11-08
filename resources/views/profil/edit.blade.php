@extends('layouts.master')

@section('title', 'Ubah Profil')

@section('content')
    <div class="profil-top">
        <nav class="breadcrumb">
            <a href="{{ route('dashboard') }}">Beranda</a>
            <i class="bi bi-chevron-right"></i>
            <a href="{{ route('profil') }}">Profil</a>
            <i class="bi bi-chevron-right"></i>
            <span>Ubah</span>
        </nav>

        <div class="page-title">
            <i class="bi bi-pencil-square"></i>
            <div>
                <h1>Ubah Profil</h1>
                <p>Perbarui informasi akun dan data diri Anda.</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('profil.update') }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="loan-grid">
            <div class="form-group">
                <label>Username / Email</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required>
            </div>
            <div class="form-group">
                <label>Password Baru <small>kosongkan bila tidak diubah</small></label>
                <input type="password" name="password" autocomplete="new-password">
            </div>
        </div>

        @if ($user->role !== 'Siswa')
            <div class="form-group">
                <label>Nama Lengkap <small>opsional, mis. nama pustakawan</small></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" placeholder="mis. Rina Kusuma, S.IP.">
            </div>
        @endif

        @if ($user->role === 'Siswa')
            <div class="loan-grid">
                <div class="form-group">
                    <label>NIS</label>
                    <input type="text" name="nis" value="{{ old('nis', $siswa->nis ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_siswa" value="{{ old('nama_siswa', $siswa->nama_siswa ?? '') }}" required>
                </div>
            </div>

            <div class="loan-grid">
                <div class="form-group">
                    <label>Kelas</label>
                    <input type="text" name="kelas" value="{{ old('kelas', $siswa->kelas ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>No. Telepon <small>opsional</small></label>
                    <input type="text" name="no_telp" value="{{ old('no_telp', $siswa->no_telp ?? '') }}">
                </div>
            </div>

            <div class="loan-grid">
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin">
                        <option value="">- Pilih -</option>
                        <option value="Laki-laki" @selected(old('jenis_kelamin', $siswa->jenis_kelamin ?? '') === 'Laki-laki')>Laki-laki</option>
                        <option value="Perempuan" @selected(old('jenis_kelamin', $siswa->jenis_kelamin ?? '') === 'Perempuan')>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tempat Lahir <small>opsional</small></label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $siswa->tempat_lahir ?? '') }}">
                </div>
            </div>

            <div class="loan-grid">
                <div class="form-group">
                    <label>Tanggal Lahir <small>opsional</small></label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $siswa->tanggal_lahir ?? '') }}">
                </div>
            </div>

            <div class="form-group">
                <label>Alamat <small>opsional</small></label>
                <textarea name="alamat" rows="3">{{ old('alamat', $siswa->alamat ?? '') }}</textarea>
            </div>
        @endif

        <div class="loan-actions">
            <a href="{{ route('profil') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

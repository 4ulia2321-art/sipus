@extends('layouts.master')

@section('title', 'Edit Pengguna')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-person-gear"></i>
            <div>
                <h1>Edit Pengguna</h1>
                <p>Perbarui data pengguna dan perannya.</p>
            </div>
        </div>
        <a href="{{ route('admin.akun') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.akun.update', $akun->id_user) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Nama Lengkap <small>opsional, mis. nama pustakawan</small></label>
            <input type="text" name="name" value="{{ old('name', $akun->name) }}" placeholder="mis. Rina Kusuma, S.IP.">
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Username / Email</label>
                <input type="text" name="username" value="{{ old('username', $akun->username) }}" required>
            </div>
            <div class="form-group">
                <label>Password Baru <small>kosongkan bila tidak diubah</small></label>
                <input type="password" name="password">
            </div>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                @foreach (['Admin', 'Pustakawan', 'Siswa'] as $role)
                    <option value="{{ $role }}" @selected(old('role', $akun->role) === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Terkait Anggota</label>
            <input type="text" readonly
                   value="{{ $anggota ? $anggota->nama_siswa.' — '.$anggota->nis.' ('.$anggota->kelas.')' : 'Tidak terkait anggota' }}">
        </div>

        <div class="loan-actions">
            <a href="{{ route('admin.akun') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
@endsection

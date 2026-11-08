@extends('layouts.master')

@section('title', 'Tambah Anggota')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-person-plus"></i>
            <div>
                <h1>Tambah Anggota</h1>
                <p>Daftarkan anggota perpustakaan beserta akun masuknya.</p>
            </div>
        </div>
        <a href="{{ route($area.'.anggota') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route($area.'.anggota.store') }}" method="POST" class="form-card">
        @csrf

        <div class="loan-grid">
            <div class="form-group">
                <label>Username (otomatis dari NIS)</label>
                <input type="text" name="username" id="username" value="{{ old('username') }}" required readonly autocomplete="off" spellcheck="false">
                <small class="muted">Username akan diisi otomatis: <code>{nis}@siswa.sipus.sch.id</code></small>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>NIS</label>
                <input type="text" name="nis" value="{{ old('nis') }}" required>
            </div>
            <div class="form-group">
                <label>Nama Anggota</label>
                <input type="text" name="nama_siswa" value="{{ old('nama_siswa') }}" required>
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Kelas</label>
                <select name="kelas" required>
                    <option value="">Pilih Kelas</option>
                    @foreach ($daftarKelas as $k)
                        <option value="{{ $k }}" @selected(old('kelas') === $k)>{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>No. Telepon <small>opsional</small></label>
                <input type="text" name="no_telp" value="{{ old('no_telp') }}">
            </div>
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin">
                    <option value="">Pilih jenis kelamin</option>
                    <option value="Laki-laki" @selected(old('jenis_kelamin') === 'Laki-laki')>Laki-laki</option>
                    <option value="Perempuan" @selected(old('jenis_kelamin') === 'Perempuan')>Perempuan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}">
            </div>
        </div>

        <div class="form-group">
            <label>Tanggal Lahir</label>
            <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}">
        </div>

        <div class="form-group">
            <label>Alamat <small>opsional</small></label>
            <textarea name="alamat" rows="3">{{ old('alamat') }}</textarea>
        </div>

        <div class="loan-actions">
            <a href="{{ route($area.'.anggota') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Anggota
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const nis = document.querySelector('input[name="nis"]');
            const username = document.querySelector('input[name="username"]');
            if (!nis || !username) return;

            const sync = function () {
                const v = (nis.value || '').trim();
                username.value = v ? v + '@siswa.sipus.sch.id' : '';
            };

            nis.addEventListener('input', sync);
            sync();
        });
    </script>
    @endpush
@endsection

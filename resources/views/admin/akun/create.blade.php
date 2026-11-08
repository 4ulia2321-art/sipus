@extends('layouts.master')

@section('title', 'Tambah Pengguna')
@section('role', 'admin')

@section('content')
    @include('partials.profile-card')

    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-person-plus"></i>
            <div>
                <h1>Tambah Pengguna</h1>
                <p>Buat pengguna baru beserta perannya.</p>
            </div>
        </div>
        <a href="{{ route('admin.akun') }}" class="btn-outline desktop-only">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.akun.store') }}" method="POST" class="form-card" id="akunForm">
        @csrf

        <div class="form-group">
            <label>Terkait Anggota <small>opsional — cari &amp; pilih anggota</small></label>
            <input type="text" id="anggotaInput" list="anggotaList"
                   placeholder="Cari nama / NIS anggota..." autocomplete="off">
            <datalist id="anggotaList">
                @foreach ($anggotaList as $g)
                    <option value="{{ $g->nama_siswa }} — {{ $g->nis }} ({{ $g->kelas }}){{ $g->username ? ' • akun: '.$g->username : ' • belum ada akun' }}"
                            data-id="{{ $g->id_siswa }}" data-nama="{{ $g->nama_siswa }}"></option>
                @endforeach
            </datalist>
            <input type="hidden" name="id_siswa" id="idSiswa">
            @error('id_siswa') <small class="field-error">{{ $message }}</small> @enderror
            <small class="form-hint">Memilih anggota otomatis mengisi Nama Lengkap dan menautkan akun ke data siswa tersebut.</small>
        </div>

        <div class="form-group">
            <label>Nama Lengkap <small>opsional, mis. nama pustakawan</small></label>
            <input type="text" name="name" id="akunName" value="{{ old('name') }}" placeholder="mis. Rina Kusuma, S.IP.">
        </div>

        <div class="loan-grid">
            <div class="form-group">
                <label>Username / Email</label>
                <input type="text" name="username" value="{{ old('username') }}" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role" id="role" required>
                <option value="">-- Pilih role --</option>
                @foreach (['Admin', 'Pustakawan', 'Siswa'] as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </div>

        <div class="alert alert-info">
            Pilih <strong>Terkait Anggota</strong> untuk menautkan akun ke data siswa yang sudah ada. Data anggota juga bisa dikelola melalui menu <strong>Anggota</strong>.
        </div>

        <div class="loan-actions">
            <a href="{{ route('admin.akun') }}" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save"></i> Simpan Pengguna
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var input = document.getElementById('anggotaInput');
            var list = document.getElementById('anggotaList');
            var hidden = document.getElementById('idSiswa');
            var nama = document.getElementById('akunName') || document.querySelector('[name="name"]');
            var form = document.getElementById('akunForm');
            if (!input || !list) return;

            var byLabel = {};
            Array.prototype.forEach.call(list.options, function (o) {
                byLabel[o.value] = { id: o.getAttribute('data-id'), nama: o.getAttribute('data-nama') || '' };
            });

            function sync() {
                var g = byLabel[input.value];
                hidden.value = g ? g.id : '';
                if (g && nama) nama.value = g.nama;
            }

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            input.addEventListener('click', function () {
                if (input.showPicker) { try { input.showPicker(); } catch (e) {} }
            });

            if (form) {
                form.addEventListener('submit', function (e) {
                    var g = byLabel[input.value];
                    if (input.value.trim() !== '' && !g) {
                        if (!confirm('Anggota "' + input.value + '" tidak dikenali. Akun akan dibuat tanpa tautan anggota. Lanjutkan?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        })();
    </script>
@endpush

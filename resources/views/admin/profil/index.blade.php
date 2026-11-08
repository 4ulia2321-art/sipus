@extends('layouts.master')

@section('title', 'Profil')

@section('content')
    <div class="greeting">
        <h1>Profil Saya</h1>
        <p>Informasi akun dan data diri pengguna SIPUS.</p>
    </div>

    <section class="panel"
        style="max-width: 620px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px;">
        <div class="profile-avatar"><i class="bi bi-person"></i></div>
        <h2 style="margin: 8px 0 0; font-size: 20px;">{{ $user->name ?: $user->username }}</h2>
        <span class="status-pill">{{ $user->role }}</span>

        <dl class="profile-detail">
            <div><dt>Nama</dt><dd>{{ $user->name ?: '-' }}</dd></div>
            <div><dt>Username</dt><dd>{{ $user->username }}</dd></div>
            <div><dt>Role</dt><dd>{{ $user->role }}</dd></div>
            @if ($siswa)
                <div><dt>NIS</dt><dd>{{ $siswa->nis }}</dd></div>
                <div><dt>Nama</dt><dd>{{ $siswa->nama_siswa }}</dd></div>
                <div><dt>Kelas</dt><dd>{{ $siswa->kelas }}</dd></div>
                <div><dt>No. Telp</dt><dd>{{ $siswa->no_telp ?? '-' }}</dd></div>
            @endif
            @if ($jumlahPeminjaman !== null)
                <div><dt>Total Peminjaman</dt><dd>{{ $jumlahPeminjaman }} kali</dd></div>
            @endif
        </dl>

        <a href="{{ route('profil.edit') }}" class="btn-primary" style="margin-top: 8px;">
            <i class="bi bi-pencil-square"></i> Ubah Profil
        </a>

        <form action="{{ route('logout') }}" method="POST" style="margin-top: 8px;">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </button>
        </form>
    </section>
@endsection
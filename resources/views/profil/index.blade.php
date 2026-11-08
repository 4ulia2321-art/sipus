@extends('layouts.master')

@section('title', 'Profil')

@section('content')
    <div class="profil-top">
        <nav class="breadcrumb">
            <a href="{{ route('dashboard') }}">Beranda</a>
            <i class="bi bi-chevron-right"></i>
            <span>Profil</span>
        </nav>

        <div class="page-title">
            <i class="bi bi-person-fill"></i>
            <div>
                <h1>Profil</h1>
                <p>Kelola informasi akun Anda.</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @php
        $ttl = ($siswa->tempat_lahir || $siswa->tanggal_lahir)
            ? trim(
                ($siswa->tempat_lahir ?? '') .
                ($siswa->tempat_lahir && $siswa->tanggal_lahir ? ', ' : '') .
                ($siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d F Y') : ''),
                ', '
            )
            : '-';
    @endphp

    <div class="profile-grid">

        {{-- Kartu profil --}}
        <section class="card profile-card">
            <div class="profile-photo {{ $siswa->foto ? 'has-photo' : '' }}">
                @if ($siswa->foto)
                    <img src="{{ asset('storage/'.$siswa->foto) }}" alt="{{ $siswa->nama }}">
                @else
                    <i class="bi bi-person-fill"></i>
                @endif
                <a href="{{ route('profil.edit') }}" class="photo-btn" aria-label="Ubah foto profil"><i class="bi bi-camera-fill"></i></a>
            </div>
            <h3>{{ $siswa->nama }}</h3>
            <p class="role">Siswa</p>

            <ul class="mini-info">
                <li><i class="bi bi-person-badge"></i><span>NIS</span><b>: {{ $siswa->nis }}</b></li>
                <li><i class="bi bi-envelope"></i><span>Kelas</span><b>: {{ $siswa->kelas }}</b></li>
                <li><i class="bi bi-calendar3"></i><span>Tahun Ajaran</span><b>: {{ $siswa->tahun_ajaran ?: '-' }}</b></li>
            </ul>

            <a href="{{ route('profil.edit') }}" class="btn btn-primary btn-block">
                <i class="bi bi-pencil-fill"></i> Ubah Profil
            </a>
        </section>

        <div class="profile-right">
            {{-- Informasi pribadi --}}
            <section class="card">
                <div class="card-head">
                    <h3><i class="bi bi-person-fill"></i> Informasi Pribadi</h3>
                    <a href="{{ route('profil.edit') }}" class="btn btn-outline btn-sm">
                        <i class="bi bi-pencil-square"></i> Edit
                    </a>
                </div>
                <dl class="info-list">
                    @foreach ([
                        'Nama Lengkap'          => $siswa->nama,
                        'NIS'                   => $siswa->nis,
                        'Kelas'                 => $siswa->kelas,
                        'Jenis Kelamin'         => $siswa->jenis_kelamin ?: '-',
                        'Tempat, Tanggal Lahir' => $ttl,
                        'Alamat'                => $siswa->alamat ?: '-',
                        'Email'                 => $siswa->email ?: '-',
                        'No. HP'                => $siswa->no_hp ?: '-',
                    ] as $label => $value)
                        <div class="info-row">
                            <dt>{{ $label }}</dt>
                            <dd>: {{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Ganti password --}}
            <section class="card password-card">
                <i class="bi bi-lock-fill lock-icon"></i>
                <div class="grow">
                    <h3>Ganti Password</h3>
                    <p>Gunakan password yang kuat untuk keamanan akun Anda.</p>
                </div>
                <a href="{{ route('profil.edit') }}" class="btn btn-outline">
                    <i class="bi bi-lock-fill"></i> Ganti Password
                </a>
            </section>
        </div>
    </div>

    <div class="table-grid">
        {{-- Buku sedang dipinjam --}}
        <section class="card">
            <div class="card-head">
                <h3><i class="bi bi-book"></i> Buku yang Sedang Dipinjam</h3>
                <a href="{{ route('riwayat') }}" class="link-all">Lihat Semua</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th><th>Judul Buku</th><th>Tanggal Pinjam</th><th>Jatuh Tempo</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sedangDipinjam as $i => $p)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $p->buku->judul }}</td>
                            <td>{{ $p->tanggal_pinjam->translatedFormat('d M Y') }}</td>
                            <td>{{ $p->jatuh_tempo->translatedFormat('d M Y') }}</td>
                            <td><span class="badge badge-info">Dipinjam</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Tidak ada buku yang sedang dipinjam.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Riwayat --}}
        <section class="card">
            <div class="card-head">
                <h3><i class="bi bi-clock-history"></i> Riwayat Peminjaman</h3>
                <a href="{{ route('riwayat') }}" class="link-all">Lihat Semua</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th><th>Judul Buku</th><th>Tanggal Pinjam</th><th>Tanggal Kembali</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $i => $p)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $p->buku->judul }}</td>
                            <td>{{ $p->tanggal_pinjam->translatedFormat('d M Y') }}</td>
                            <td>{{ optional($p->tanggal_kembali)->translatedFormat('d M Y') }}</td>
                            <td><span class="badge badge-success">Dikembalikan</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Belum ada riwayat peminjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection

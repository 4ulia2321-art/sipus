@extends('layouts.master')

@section('title', 'Detail Anggota')
@section('role', 'pustakawan')

@push('styles')
    {{-- buku.css: komponen bersama (banner pendukung, tombol) --}}
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota-pustakawan.css') }}">
@endpush

@section('content')
    @php
        $sekarang = \Carbon\Carbon::now()->locale('id');
    @endphp

    {{-- Banner judul --}}
    <section class="page-banner">
        <span class="banner-icon"><i class="bi bi-person-badge"></i></span>
        <div class="banner-text">
            <h2>Detail Anggota</h2>
            <p class="hide-mobile">Informasi lengkap data anggota perpustakaan.</p>
            <p class="show-mobile">Informasi data anggota.</p>
        </div>
        <div class="date-chip hide-mobile">
            <i class="bi bi-calendar3"></i>
            <div>
                <span>{{ $sekarang->translatedFormat('l, j F Y') }}</span>
                <span>{{ $sekarang->format('H:i') }} WIB</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h3>{{ $anggota->nama_siswa }}</h3>
            <span class="status-badge status-dikembalikan">Aktif</span>
        </div>

        <dl class="detail-meta">
            <div>
                <dt><i class="bi bi-person"></i> Nama Anggota</dt>
                <dd>{{ $anggota->nama_siswa }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-hash"></i> NIS</dt>
                <dd>{{ $anggota->nis }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-mortarboard"></i> Kelas</dt>
                <dd>{{ $anggota->kelas }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-gender-ambiguous"></i> Jenis Kelamin</dt>
                <dd>{{ $anggota->jenis_kelamin ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-geo-alt"></i> Tempat Lahir</dt>
                <dd>{{ $anggota->tempat_lahir ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-event"></i> Tanggal Lahir</dt>
                <dd>{{ $anggota->tanggal_lahir ? \Carbon\Carbon::parse($anggota->tanggal_lahir)->translatedFormat('d F Y') : '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-at"></i> Username</dt>
                <dd>{{ $anggota->username ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-telephone"></i> No. Telepon</dt>
                <dd>{{ $anggota->no_telp ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-geo-alt"></i> Alamat</dt>
                <dd>{{ $anggota->alamat ?? '-' }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-journal-text"></i> Total Peminjaman</dt>
                <dd>{{ $anggota->total_pinjam }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-book-half"></i> Sedang Dipinjam</dt>
                <dd>{{ $anggota->sedang_pinjam }}</dd>
            </div>
        </dl>

        <div class="loan-actions" style="margin-top: 18px;">
            <a href="{{ route('pustakawan.anggota') }}" class="btn-outline">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <a href="{{ route('pustakawan.anggota.edit', $anggota->id_siswa) }}" class="btn-outline">
                <i class="bi bi-pencil"></i> Edit
            </a>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h3>Riwayat Peminjaman</h3>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Judul Buku</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $i => $row)
                        @php
                            $jatuhTempo = \Carbon\Carbon::parse($row->tanggal_jatuh_tempo);
                            if ($row->tanggal_kembali) {
                                $status = 'Dikembalikan';
                            } elseif ($row->status === 'Dipinjam' && $jatuhTempo->lt(\Carbon\Carbon::today())) {
                                $status = 'Terlambat';
                            } else {
                                $status = $row->status;
                            }
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->judul }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->tanggal_pinjam)->translatedFormat('d M Y') }}</td>
                            <td>{{ $jatuhTempo->translatedFormat('d M Y') }}</td>
                            <td>
                                <span class="status-badge status-{{ strtolower($status) }}">{{ $status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Belum ada riwayat peminjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

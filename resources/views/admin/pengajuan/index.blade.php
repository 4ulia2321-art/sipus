@extends('layouts.master')

@section('title', 'Pengajuan Peminjaman')

@section('content')
    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Beranda</a>
        <i class="bi bi-chevron-right"></i>
        @if ($hanyaMenunggu ?? false)
            <a href="{{ route('pustakawan.pengajuan') }}">Pengajuan Peminjaman</a>
            <i class="bi bi-chevron-right"></i>
        @endif
        <span>{{ $judulHalaman ?? 'Pengajuan Peminjaman' }}</span>
    </nav>

    <div class="page-title">
        <i class="bi {{ ($hanyaMenunggu ?? false) ? 'bi-check2-square' : 'bi-file-earmark-text' }}"></i>
        <div>
            <h1>{{ $judulHalaman ?? 'Pengajuan Peminjaman' }}</h1>
            <p>{{ $deskripsiHalaman ?? ($isAdmin ? 'Periksa dan proses pengajuan peminjaman yang masuk.' : 'Pilih buku yang ingin dipinjam dan pantau status pengajuanmu.') }}</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Daftar buku tersedia untuk diajukan (khusus siswa) --}}
    @unless ($isAdmin)
        <section class="panel">
            <div class="panel-head">
                <h3><i class="bi bi-book"></i> Buku Tersedia untuk Dipinjam</h3>
            </div>
            <div class="catalog-grid">
                @forelse ($bukuList as $buku)
                    <div class="book-card">
                        <div class="book-cover cover-sm">
                            @if ($buku->cover)
                                <img src="{{ asset('storage/' . $buku->cover) }}" alt="{{ $buku->judul }}">
                            @else
                                <i class="bi bi-image"></i>
                            @endif
                        </div>
                        <div class="book-body">
                            <h4>{{ $buku->judul }}</h4>
                            <small>{{ $buku->pengarang }}</small>
                        </div>
                        <a href="{{ route('pengajuan.create', $buku->id) }}" class="btn-detail btn-ajukan">
                            <i class="bi bi-plus-circle"></i> Ajukan Peminjaman
                        </a>
                    </div>
                @empty
                    <p class="empty">Belum ada buku tersedia untuk dipinjam.</p>
                @endforelse
            </div>
        </section>
    @endunless

    {{-- Daftar pengajuan --}}
    <section class="panel">
        <div class="panel-head">
            <h3><i class="bi bi-clock-history"></i> {{ ($hanyaMenunggu ?? false) ? 'Menunggu Persetujuan' : 'Daftar Pengajuan' }}</h3>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Siswa</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Buku</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        @if ($isAdmin && ($hanyaMenunggu ?? false))
                            <th>Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengajuan as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->nama_pemohon }}</td>
                            <td>{{ $row->nis }}</td>
                            <td>{{ $row->kelas }}</td>
                            <td>{{ $row->judul }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->tanggal_jatuh_tempo)->translatedFormat('d F Y') }}</td>
                            <td>
                                <span class="status-pill status-{{ strtolower($row->status) }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                            @if ($isAdmin && ($hanyaMenunggu ?? false))
                                <td class="table-actions">
                                    <form action="{{ route('pustakawan.pengajuan.approve', $row->id_peminjaman) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn-sm btn-ok"><i class="bi bi-check-lg"></i> Setujui</button>
                                    </form>
                                    <form action="{{ route('pustakawan.pengajuan.reject', $row->id_peminjaman) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn-sm btn-danger"><i class="bi bi-x-lg"></i> Tolak</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin && ($hanyaMenunggu ?? false) ? 8 : 7 }}" class="empty">
                                {{ ($hanyaMenunggu ?? false) ? 'Tidak ada pengajuan yang menunggu persetujuan.' : 'Belum ada pengajuan peminjaman.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
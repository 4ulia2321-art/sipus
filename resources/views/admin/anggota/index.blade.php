@extends('layouts.master')

@section('title', 'Data Anggota')
@section('role', 'admin')
@section('search_placeholder', 'Cari nama, NIS, atau kelas...')

@push('styles')
    {{-- buku.css berisi komponen bersama (tombol, filter, tabel, pagination) --}}
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota.css') }}">
@endpush

@section('content')
    @php
        $jenisClass  = ['Siswa' => 'blue', 'Guru' => 'violet', 'Staf TU' => 'yellow'];
        $statusClass = ['Aktif' => 'green', 'Nonaktif' => 'red'];
    @endphp

    {{-- Header halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-people-fill"></i>
            <div>
                <h2>Data Anggota</h2>
                <p>Kelola dan pantau seluruh data anggota perpustakaan SMPN 01 Pasirjambu.</p>
            </div>
        </div>
        <a href="{{ route($area.'.anggota.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Anggota
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
    @endif

    {{-- Kartu ringkasan --}}
    <section class="stats" data-live-part="stats">
        @foreach ($stats as $s)
            <div class="stat-card {{ $s['color'] }}">
                <div class="stat-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                <h4>{{ $s['label'] }}</h4>
                <div class="stat-value">{{ number_format($s['value'], 0, ',', '.') }}</div>
                <div class="stat-delta"><i class="bi bi-arrow-up"></i> <b>{{ $s['delta'] }}</b> dari bulan lalu</div>
            </div>
        @endforeach
    </section>

    {{-- Filter --}}
    <form method="GET" action="{{ route($area.'.anggota') }}" class="filter-bar" data-live-form>
        <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, NIS, atau kelas...">
        </div>

        <select name="jenis" onchange="this.form.submit()">
            <option value="">Semua Jenis Anggota</option>
            @foreach ($daftarJenis as $j)
                <option value="{{ $j }}" @selected(request('jenis') === $j)>{{ $j }}</option>
            @endforeach
        </select>

        <select name="kelas" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($daftarKelas as $k)
                <option value="{{ $k }}" @selected(request('kelas') === $k)>{{ $k }}</option>
            @endforeach
        </select>

        <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach ($daftarStatus as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
            @endforeach
        </select>

        <a href="{{ route($area.'.anggota') }}" class="btn btn-outline"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>

    {{-- Tabel --}}
    <div class="card table-card" data-live-part="list">
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th class="c-check"><input type="checkbox" id="checkAll"></th>
                        <th>No</th>
                        <th>Foto</th>
                        <th>Nama Anggota</th>
                        <th>NIS/NIP</th>
                        <th>Kelas/Jabatan</th>
                        <th>Jenis Anggota</th>
                        <th>Status</th>
                        <th class="t-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggota as $a)
                        <tr>
                            <td class="c-check"><input type="checkbox" class="row-check" value="{{ $a['id'] }}"></td>
                            <td>{{ $a['no'] }}</td>
                            <td>
                                @if (! empty($a['foto']))
                                    <img class="avatar" src="{{ asset('storage/' . $a['foto']) }}" alt="{{ $a['nama'] }}">
                                @else
                                    <span class="avatar" style="background: {{ $a['warna'] }}">
                                        {{ \Illuminate\Support\Str::of($a['nama'])->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                    </span>
                                @endif
                            </td>
                            <td class="judul">{{ $a['nama'] }}</td>
                            <td>{{ $a['nomor'] }}</td>
                            <td>{{ $a['kelas'] }}</td>
                            <td><span class="pill {{ $jenisClass[$a['jenis']] ?? 'blue' }}">{{ $a['jenis'] }}</span></td>
                            <td><span class="pill {{ $statusClass[$a['status']] ?? 'green' }}">{{ $a['status'] }}</span></td>
                            <td>
                                <div class="actions center">
                                    <a href="{{ route($area.'.anggota.show', $a['id']) }}" class="act view" title="Lihat"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route($area.'.anggota.edit', $a['id']) }}" class="act edit" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route($area.'.anggota.destroy', $a['id']) }}" method="POST"
                                          onsubmit="return confirm('Hapus anggota &quot;{{ $a['nama'] }}&quot;?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act del" title="Hapus" @disabled(! $a['bisa_hapus'])>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty">Tidak ada anggota yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-foot">
            <span class="info">
                @if ($anggota->total())
                    Menampilkan {{ number_format($anggota->firstItem(), 0, ',', '.') }} - {{ number_format($anggota->lastItem(), 0, ',', '.') }}
                    dari {{ number_format($anggota->total(), 0, ',', '.') }} anggota
                @else
                    Menampilkan 0 anggota
                @endif
            </span>
            {{ $anggota->links('partials.pagination') }}
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('checkAll')?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    });
</script>
@endpush

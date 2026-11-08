@extends('layouts.master')

@section('title', 'Data Anggota')
@section('role', 'pustakawan')
@section('search_placeholder', 'Cari nama, NIS, atau kelas...')

@push('styles')
    {{-- buku.css: komponen bersama (filter, tombol aksi, pill, footer tabel) --}}
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/anggota-pustakawan.css') }}">
@endpush

@section('content')
    @php
        $sekarang    = \Carbon\Carbon::now()->locale('id');
        $statusClass = ['Aktif' => 'green', 'Tidak Aktif' => 'red'];
    @endphp

    {{-- Banner judul --}}
    <section class="page-banner">
        <span class="banner-icon"><i class="bi bi-people-fill"></i></span>
        <div class="banner-text">
            <h2>Data Anggota</h2>
            <p class="hide-mobile">Kelola data anggota perpustakaan SMP Negeri 01 Pasirjambu.</p>
            <p class="show-mobile">Kelola data anggota perpustakaan.</p>
        </div>
        <div class="date-chip hide-mobile">
            <i class="bi bi-calendar3"></i>
            <div>
                <span>{{ $sekarang->translatedFormat('l, j F Y') }}</span>
                <span>{{ $sekarang->format('H:i') }} WIB</span>
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
    @endif

    <div class="card list-card">
        {{-- Filter --}}
        <form method="GET" action="{{ route('pustakawan.anggota') }}" class="filter-bar">
            <div class="filter-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, NIS, atau kelas...">
            </div>

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

            <a href="{{ route('pustakawan.anggota.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Tambah Anggota
            </a>
        </form>

        {{-- Tabel (desktop) --}}
        <div class="table-box hide-mobile">
            <table class="table roomy">
                <thead>
                    <tr>
                        <th class="c-check"><input type="checkbox" id="checkAll"></th>
                        <th>No</th>
                        <th>Nama Anggota</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggota as $a)
                        <tr>
                            <td class="c-check"><input type="checkbox" class="row-check" value="{{ $a['id'] }}"></td>
                            <td>{{ $a['no'] }}</td>
                            <td>{{ $a['nama'] }}</td>
                            <td>{{ $a['nis'] }}</td>
                            <td>{{ $a['kelas'] }}</td>
                            <td><span class="pill {{ $statusClass[$a['status']] ?? 'green' }}">{{ $a['status'] }}</span></td>
                            <td>@include('pustakawan.anggota._aksi', ['a' => $a])</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">Tidak ada anggota yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <ul class="member-list show-mobile">
            @forelse ($anggota as $a)
                <li>
                    @if (! empty($a['foto']))
                        <img class="avatar lg" src="{{ asset('storage/' . $a['foto']) }}" alt="{{ $a['nama'] }}">
                    @else
                        <span class="avatar lg"><i class="bi bi-person-fill"></i></span>
                    @endif
                    <div class="m-info">
                        <strong>{{ $a['nama'] }}</strong>
                        <small>NIS {{ $a['nis'] }} &bull; {{ $a['kelas'] }}</small>
                        <span class="pill sm {{ $statusClass[$a['status']] ?? 'green' }}">{{ $a['status'] }}</span>
                    </div>
                    <div class="m-aksi">@include('pustakawan.anggota._aksi', ['a' => $a, 'small' => true])</div>
                </li>
            @empty
                <li class="empty">Tidak ada anggota yang cocok dengan pencarian.</li>
            @endforelse
        </ul>

        <div class="table-foot">
            <span class="info">
                @if ($anggota->total())
                    Menampilkan {{ $anggota->firstItem() }} - {{ $anggota->lastItem() }} dari {{ number_format($anggota->total(), 0, ',', '.') }} data
                @else
                    Menampilkan 0 data
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

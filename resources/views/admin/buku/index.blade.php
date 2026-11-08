@extends('layouts.master')

@section('title', 'Kelola Buku')
@section('role', 'admin')
@section('search_placeholder', 'Cari judul, pengarang, atau nomor seri buku...')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
@endpush

@section('content')
    @php
        $kategoriClass = [
            'Fiksi' => 'violet', 'Sastra' => 'blue', 'Pelajaran' => 'blue', 'Sejarah' => 'blue',
            'IPA' => 'green', 'Teknologi' => 'violet', 'Bahasa' => 'red', 'Cerita' => 'violet',
        ];
        $statusClass = ['Tersedia' => 'green', 'Hampir Habis' => 'yellow', 'Habis' => 'red'];
    @endphp

    {{-- Header halaman --}}
    <div class="page-head">
        <div class="page-title">
            <i class="bi bi-book-half"></i>
            <div>
                <h2>Kelola Buku</h2>
                <p>Kelola dan pantau seluruh koleksi buku di perpustakaan SMPN 01 Pasirjambu.</p>
            </div>
        </div>
        <a href="{{ route('admin.buku.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Buku
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
    @endif

    {{-- Kartu ringkasan --}}
    <section class="bk-stats" data-live-part="stats">
        @foreach ($stats as $s)
            <div class="bk-stat {{ $s['color'] }}">
                <div class="bk-stat-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                <div class="bk-stat-body">
                    <h4>{{ $s['label'] }}</h4>
                    <div class="bk-stat-value">{{ number_format($s['value'], 0, ',', '.') }}</div>
                </div>
                <div class="bk-stat-foot"><i class="bi {{ $s['mini'] }}"></i><span>{{ $s['sub'] }}</span></div>
            </div>
        @endforeach
    </section>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.buku') }}" class="filter-bar" data-live-form>
        <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari buku berdasarkan judul, pengarang, atau nomor seri...">
        </div>

        <select name="kategori" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            @foreach ($daftarKategori as $k)
                <option value="{{ $k }}" @selected(request('kategori') === $k)>{{ $k }}</option>
            @endforeach
        </select>

        <select name="tahun" onchange="this.form.submit()">
            <option value="">Semua Tahun</option>
            @foreach ($daftarTahun as $t)
                <option value="{{ $t }}" @selected((string) request('tahun') === (string) $t)>{{ $t }}</option>
            @endforeach
        </select>

        <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach ($daftarStatus as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
            @endforeach
        </select>

        <a href="{{ route('admin.buku') }}" class="btn btn-outline"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>

    {{-- Tabel --}}
    <div class="card table-card" data-live-part="list">
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th class="c-check"><input type="checkbox" id="checkAll"></th>
                        <th>No</th>
                        <th>Sampul</th>
                        <th>No Seri Buku</th>
                        <th>Judul Buku <i class="bi bi-chevron-expand sort"></i></th>
                        <th>Pengarang <i class="bi bi-chevron-expand sort"></i></th>
                        <th>Penerbit</th>
                        <th>Kategori</th>
                        <th>Tahun Terbit <i class="bi bi-chevron-expand sort"></i></th>
                        <th>Lokasi Rak</th>
                        <th>Stok <i class="bi bi-chevron-expand sort"></i></th>
                        <th>Status <i class="bi bi-chevron-expand sort"></i></th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($buku as $b)
                        <tr>
                            <td class="c-check"><input type="checkbox" class="row-check" value="{{ $b['id'] }}"></td>
                            <td>{{ $b['no'] }}</td>
                            <td>
                                <div class="cover" style="background: linear-gradient(160deg, {{ $b['cover'][0] }}, {{ $b['cover'][1] }});">
                                    <span>{{ \Illuminate\Support\Str::limit($b['judul'], 14, '') }}</span>
                                </div>
                            </td>
                            <td>{{ $b['seri'] }}</td>
                            <td class="judul">{{ $b['judul'] }}</td>
                            <td>{{ $b['pengarang'] }}</td>
                            <td>{{ $b['penerbit'] }}</td>
                            <td><span class="pill {{ $kategoriClass[$b['kategori']] ?? 'blue' }}">{{ $b['kategori'] }}</span></td>
                            <td>{{ $b['tahun'] }}</td>
                            <td>{{ $b['rak'] }}</td>
                            <td>{{ $b['stok'] }}</td>
                            <td><span class="pill {{ $statusClass[$b['status']] ?? 'green' }}">{{ $b['status'] }}</span></td>
                            <td>
                                <div class="actions">
                                    <a href="{{ route('admin.buku.edit', $b['id']) }}" class="act edit" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('admin.buku.destroy', $b['id']) }}" method="POST"
                                          onsubmit="return confirm('Hapus buku &quot;{{ $b['judul'] }}&quot;?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act del" title="Hapus" @disabled(! $b['bisa_hapus'])>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="empty">Tidak ada buku yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-foot">
            <span class="info">
                @if ($buku->total())
                    Menampilkan {{ number_format($buku->firstItem(), 0, ',', '.') }} - {{ number_format($buku->lastItem(), 0, ',', '.') }}
                    dari {{ number_format($buku->total(), 0, ',', '.') }} buku
                @else
                    Menampilkan 0 buku
                @endif
            </span>
            {{ $buku->links('partials.pagination') }}
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

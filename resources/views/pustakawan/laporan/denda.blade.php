@extends('layouts.master')

@section('title', 'Laporan Denda')
@section('role', 'pustakawan')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/laporan.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/laporan-denda.css') }}">
@endpush

@section('content')
    @php
        $rupiah = fn ($nilai) => 'Rp ' . number_format((float) $nilai, 0, ',', '.');
        $labelPeriode = $mulai->isSameDay($selesai)
            ? $mulai->translatedFormat('d M Y')
            : $mulai->translatedFormat('d M Y') . ' – ' . $selesai->translatedFormat('d M Y');
        $paramPeriode = [
            'periode' => $periode,
            'mulai'   => $mulai->format('Y-m-d'),
            'selesai' => $selesai->format('Y-m-d'),
        ];
        $paramPdf = $paramPeriode + [
            'q'        => $filter['q'],
            'status'   => $filter['status'],
            'kelas'    => $filter['kelas'],
            'kategori' => $filter['kategori'],
        ];
    @endphp

    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('pustakawan.dashboard') }}">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <a href="{{ route('pustakawan.laporan') }}">Laporan</a>
        <i class="bi bi-chevron-right"></i>
        <span>Laporan Denda</span>
    </nav>

    {{-- Form filter: rentang tanggal, periode, pencarian, status, kelas, kategori --}}
    <form method="GET" action="{{ route('pustakawan.laporan.denda') }}" id="filter-denda">
        <input type="hidden" name="mulai" id="mulai" value="{{ $mulai->format('Y-m-d') }}">
        <input type="hidden" name="selesai" id="selesai" value="{{ $selesai->format('Y-m-d') }}">
        <input type="hidden" name="periode" id="periode" value="{{ $periode }}">

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-cash-coin"></i>
                <div>
                    <h1>Laporan Denda</h1>
                    <p>Denda dihitung otomatis dari jumlah hari keterlambatan × tarif harian.</p>
                </div>
            </div>

            <div class="field field-date range-field">
                <i class="bi bi-calendar3"></i>
                <input type="text" id="rentang" placeholder="Pilih rentang tanggal" readonly>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>

        <div class="periode-bar">
            <span class="periode-label"><i class="bi bi-calendar-range"></i> Periode laporan:</span>
            <div class="periode-tabs">
                @foreach (['harian' => 'Harian', 'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan'] as $key => $label)
                    <a href="{{ route('pustakawan.laporan.denda', array_merge(request()->query(), ['periode' => $key, 'page' => null])) }}"
                       class="periode-tab {{ $periode === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
                <a href="#" class="periode-tab {{ $periode === 'kustom' ? 'active' : '' }}"
                   onclick="event.preventDefault(); bukaRentang();"
                   title="Pilih rentang tanggal sendiri">Kustom</a>
            </div>
            <span class="periode-info">
                {{ $mulai->translatedFormat('d M Y') }} &ndash; {{ $selesai->translatedFormat('d M Y') }}
            </span>
        </div>

        <div class="filter-bar">
            <div class="filter-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="Cari nama siswa, NIS, kelas, atau judul buku...">
            </div>

            <select name="status" onchange="this.form.submit()">
                <option value="">Semua Status Bayar</option>
                @foreach (['Belum Lunas', 'Lunas'] as $status)
                    <option value="{{ $status }}" @selected($filter['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>

            <select name="kelas" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                @foreach ($kelasList as $kelas)
                    <option value="{{ $kelas }}" @selected($filter['kelas'] === $kelas)>{{ $kelas }}</option>
                @endforeach
            </select>

            <select name="kategori" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                @foreach (['Terlambat', 'Tepat Waktu'] as $kategori)
                    <option value="{{ $kategori }}" @selected($filter['kategori'] === $kategori)>{{ $kategori }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel"></i> Terapkan
            </button>
            <a href="{{ route('pustakawan.laporan.denda') }}" class="btn btn-outline">
                <i class="bi bi-arrow-clockwise"></i> Reset
            </a>
        </div>
    </form>

    {{-- Navigasi tab laporan: selalu terlihat di bagian atas halaman --}}
    <div class="card report-tabs-card">
        <div class="tabs-row">
            <div class="tabs">
                <a href="{{ route('pustakawan.laporan', $paramPeriode + ['tab' => 'peminjaman']) }}"
                   class="tab">Peminjaman</a>
                <a href="{{ route('pustakawan.laporan', $paramPeriode + ['tab' => 'pengembalian']) }}"
                   class="tab">Pengembalian</a>
                <span class="tab active">Denda</span>
            </div>

            <a href="{{ route('pustakawan.laporan.denda.pdf', $paramPdf) }}"
               target="_blank" rel="noopener"
               class="btn btn-outline btn-print"
               title="Unduh laporan denda dalam format PDF A4">
                <i class="bi bi-file-earmark-pdf"></i> Cetak PDF A4
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
    @endif

    {{-- Rekap denda --}}
    <div class="r-stats">
        @php
            $cards = [
                ['icon' => 'bi-cash-stack', 'label' => 'Total Denda', 'value' => $rupiah($rekap['total_denda']), 'sub' => $rekap['jumlah_transaksi'] . ' transaksi berdenda'],
                ['icon' => 'bi-check-circle', 'label' => 'Denda Lunas', 'value' => $rupiah($rekap['lunas']), 'sub' => $rekap['jumlah_lunas'] . ' transaksi lunas'],
                ['icon' => 'bi-exclamation-triangle', 'label' => 'Denda Belum Lunas', 'value' => $rupiah($rekap['belum']), 'sub' => $rekap['jumlah_belum'] . ' transaksi menunggu pembayaran'],
                ['icon' => 'bi-speedometer2', 'label' => 'Tarif Denda', 'value' => $rupiah($tarif), 'sub' => 'Per hari keterlambatan'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="card r-stat">
                <div class="r-stat-top">
                    <div class="r-stat-icon"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div>
                        <div class="r-stat-label">{{ $card['label'] }}</div>
                        <div class="r-stat-value">{{ $card['value'] }}</div>
                    </div>
                </div>
                <div class="r-stat-sub">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Tabel rincian denda --}}
    <div class="card report-card">
        <div class="rekap-head">
            <h3 class="rekap-title"><i class="bi bi-list-check"></i> Rincian Denda</h3>
            <span class="rekap-periode">{{ $labelPeriode }}</span>
        </div>

        <div class="table-scroll">
            <table class="bk-table denda-report-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Anggota</th>
                        <th>Kelas</th>
                        <th>Judul Buku</th>
                        <th>Jatuh Tempo</th>
                        <th>Tgl Kembali</th>
                        <th>Terlambat</th>
                        <th>Denda</th>
                        <th>Status Bayar</th>
                        <th class="t-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['no'] }}</td>
                            <td>
                                <div class="judul">{{ $row['nama'] }}</div>
                                <div class="muted">{{ $row['nis'] }}</div>
                            </td>
                            <td>{{ $row['kelas'] }}</td>
                            <td class="judul">{{ $row['buku'] }}</td>
                            <td class="hide-m">{{ $row['jatuh_tempo'] }}</td>
                            <td>{{ $row['tanggal_kembali'] }}</td>
                            <td>
                                <span class="hari-denda {{ $row['hari'] > 0 ? 'late' : '' }}">{{ $row['hari'] }} hari</span>
                            </td>
                            <td class="denda-nom">{{ $rupiah($row['denda']) }}</td>
                            <td>
                                <span class="pill {{ $row['status_bayar'] === 'Lunas' ? 'green' : 'red' }}">
                                    {{ $row['status_bayar'] }}
                                </span>
                            </td>
                            <td class="t-center">
                                @if ($row['status_bayar'] !== 'Lunas')
                                    <form action="{{ route('pustakawan.denda.lunas', $row['id']) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-lunas" title="Tandai denda sebagai lunas">
                                            <i class="bi bi-check2-circle"></i> Lunaskan
                                        </button>
                                    </form>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty">Tidak ada denda yang cocok dengan periode/filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->total())
                    <tfoot>
                        <tr>
                            <td colspan="7" class="tfoot-label">Total halaman ini</td>
                            <td class="denda-nom">{{ $rupiah($rows->getCollection()->sum('denda')) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="table-foot">
            <span class="info">
                @if ($rows->total())
                    Menampilkan {{ number_format($rows->firstItem(), 0, ',', '.') }} – {{ number_format($rows->lastItem(), 0, ',', '.') }}
                    dari {{ number_format($rows->total(), 0, ',', '.') }} transaksi berdenda
                @else
                    Menampilkan 0 transaksi berdenda
                @endif
            </span>
            {{ $rows->onEachSide(1)->links('partials.pagination') }}
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script>
        const rentangDenda = flatpickr('#rentang', {
            mode: 'range',
            locale: 'id',
            altInput: true,
            altFormat: 'd M Y',
            dateFormat: 'Y-m-d',
            defaultDate: [@json($mulai->format('Y-m-d')), @json($selesai->format('Y-m-d'))],
            onChange: function (dates, str, inst) {
                if (dates.length === 2) {
                    document.getElementById('mulai').value = inst.formatDate(dates[0], 'Y-m-d');
                    document.getElementById('selesai').value = inst.formatDate(dates[1], 'Y-m-d');
                    document.getElementById('periode').value = 'kustom';
                    document.getElementById('filter-denda').submit();
                }
            }
        });

        window.bukaRentang = function () {
            rentangDenda.open();
        };
    </script>
@endpush

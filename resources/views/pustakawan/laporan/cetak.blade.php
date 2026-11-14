@php
    $judul = $tab === 'pengembalian' ? 'Laporan Pengembalian Buku' : 'Laporan Peminjaman Buku';
    $labelPeriode = $mulai->isSameDay($selesai)
        ? $mulai->translatedFormat('d F Y')
        : $mulai->translatedFormat('d F Y') . ' s.d. ' . $selesai->translatedFormat('d F Y');
    $dicetakPada = now()->translatedFormat('d F Y, H:i');
    $dicetakOleh = auth()->user()->name ?: (auth()->user()->username ?: 'Pustakawan');

    $namaPerpustakaan = \App\Support\Pengaturan::get('nama_perpustakaan', 'Perpustakaan SMP Negeri 1 Pasirjambu');
    $alamat = \App\Support\Pengaturan::get('alamat', 'Jl. Raya Pasirjambu, Kabupaten Bandung');

    $paramPeriode = [
        'periode' => $periode,
        'mulai'   => $mulai->format('Y-m-d'),
        'selesai' => $selesai->format('Y-m-d'),
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }} - {{ $labelPeriode }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #eef2f7;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 20px;
            background: #ffffff;
            border-bottom: 1px solid #d9e2ec;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        .toolbar a,
        .toolbar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #1f3a5f;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar a:hover,
        .toolbar button:hover {
            background: #f1f6fc;
        }

        .toolbar .btn-cetak {
            background: #1c447c;
            border-color: #1c447c;
            color: #ffffff;
        }

        .toolbar .btn-cetak:hover {
            background: #173763;
        }

        .toolbar .btn-aktif {
            background: #e8f1fb;
            border-color: #9fc2e8;
            color: #173763;
        }

        .filter-cetak {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .filter-cetak label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .filter-cetak input[type="date"] {
            padding: 7px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-family: inherit;
            font-size: 12px;
            color: #111827;
            background: #ffffff;
        }

        .sheet {
            max-width: 900px;
            margin: 24px auto;
            padding: 32px 36px;
            background: #ffffff;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08);
        }

        .kop {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .kop-tabel {
            width: 100%;
            border-collapse: collapse;
        }

        .kop-tabel td {
            vertical-align: middle;
        }

        .kop-logo {
            width: 78px;
            text-align: center;
        }

        .kop-logo img {
            width: 66px;
            height: 66px;
            object-fit: contain;
        }

        .kop-teks {
            padding: 0 10px;
            text-align: center;
        }

        .kop-pemkab,
        .kop-dinas {
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: 0.03em;
        }

        .kop-sekolah {
            margin-top: 1px;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: 0.05em;
        }

        .kop-perpus {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .kop-alamat {
            margin-top: 2px;
            font-size: 10px;
            color: #475569;
        }

        .garis-kop {
            height: 3px;
            margin: 9px 0 14px;
            border-top: 2.5px solid #111827;
            border-bottom: 1px solid #111827;
        }

        .kop h1 {
            margin: 0 0 3px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-align: center;
            text-transform: uppercase;
        }

        .kop .periode {
            margin: 0;
            font-size: 12.5px;
            color: #334155;
            text-align: center;
        }

        table.laporan {
            width: 100%;
            border-collapse: collapse;
        }

        table.laporan th,
        table.laporan td {
            padding: 7px 9px;
            border: 1px solid #94a3b8;
            vertical-align: top;
            font-size: 11.5px;
            line-height: 1.45;
        }

        table.laporan th {
            background: #e8eef6;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        table.laporan td.center {
            text-align: center;
        }

        table.laporan tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        table.laporan td.empty {
            padding: 18px;
            text-align: center;
            color: #64748b;
        }

        .footer-cetak {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-top: 18px;
            font-size: 11px;
            color: #475569;
        }

        .footer-cetak .kanan {
            text-align: right;
        }

        /* Rekap denda sebelum tabel laporan */
        .rekap-cetak {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .rekap-cetak-item {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
        }

        .rekap-cetak-item span {
            display: block;
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .rekap-cetak-item strong {
            display: block;
            margin-top: 2px;
            font-size: 12.5px;
            color: #111827;
        }

        .rekap-judul {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .denda-status {
            display: block;
            margin-top: 2px;
            font-size: 9.5px;
            color: #475569;
        }

        @page {
            size: A4 portrait;
            margin: 14mm 12mm;
        }

        @media print {
            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            table.laporan th,
            table.laporan td {
                font-size: 10.5px;
                padding: 6px 7px;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    {{-- Toolbar hanya tampil di layar, tidak ikut tercetak. --}}
    <div class="toolbar no-print">
        <a href="{{ route('pustakawan.laporan', array_merge(['tab' => $tab], $paramPeriode)) }}">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>

        <form method="GET" action="{{ route('pustakawan.laporan.cetak') }}" class="filter-cetak">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label>
                Dari
                <input type="date" name="mulai" value="{{ $mulai->format('Y-m-d') }}" required>
            </label>
            <label>
                Sampai
                <input type="date" name="selesai" value="{{ $selesai->format('Y-m-d') }}" required>
            </label>
            <button type="submit">
                <i class="bi bi-search"></i> Tampilkan
            </button>
        </form>

        <a href="{{ route('pustakawan.laporan.cetak', array_merge(['tab' => 'peminjaman'], $paramPeriode)) }}"
           class="{{ $tab === 'peminjaman' ? 'btn-aktif' : '' }}">
            Peminjaman
        </a>
        <a href="{{ route('pustakawan.laporan.cetak', array_merge(['tab' => 'pengembalian'], $paramPeriode)) }}"
           class="{{ $tab === 'pengembalian' ? 'btn-aktif' : '' }}">
            Pengembalian
        </a>
        <a href="{{ route('pustakawan.laporan.denda', $paramPeriode) }}">
            <i class="bi bi-cash-coin"></i> Denda
        </a>

        <button type="button" class="btn-cetak" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak Sekarang
        </button>
    </div>

    <main class="sheet">
        <header class="kop">
            <table class="kop-tabel">
                <tr>
                    <td class="kop-logo">
                        <img src="{{ asset('images/logo-smpn1-pasirjambu.png') }}" alt="Logo SMP Negeri 1 Pasirjambu">
                    </td>
                    <td class="kop-teks">
                        <div class="kop-pemkab">PEMERINTAH KABUPATEN BANDUNG</div>
                        <div class="kop-dinas">DINAS PENDIDIKAN</div>
                        <div class="kop-sekolah">SMP NEGERI 1 PASIRJAMBU</div>
                        <div class="kop-perpus">{{ $namaPerpustakaan }}</div>
                        <div class="kop-alamat">{{ $alamat }}</div>
                    </td>
                    <td class="kop-logo">
                        <img src="{{ asset('images/logo-sipus.png') }}" alt="Logo SIPUS">
                    </td>
                </tr>
            </table>

            <div class="garis-kop"></div>

            <h1>{{ $judul }}</h1>
            <p class="periode">Periode: {{ $labelPeriode }}</p>
        </header>

        <h2 class="rekap-judul">
            {{ $tab === 'pengembalian' ? 'Rekap Pengembalian' : 'Rekap Peminjaman' }} &amp; Denda
        </h2>
        <div class="rekap-cetak">
            <div class="rekap-cetak-item">
                <span>{{ $tab === 'pengembalian' ? 'Total Pengembalian' : 'Total Peminjaman' }}</span>
                <strong>{{ number_format($rekapDenda['total_transaksi'], 0, ',', '.') }}</strong>
            </div>
            <div class="rekap-cetak-item">
                <span>Total Denda</span>
                <strong>Rp {{ number_format($rekapDenda['total_denda'], 0, ',', '.') }}</strong>
            </div>
            <div class="rekap-cetak-item">
                <span>Denda Lunas</span>
                <strong>Rp {{ number_format($rekapDenda['lunas'], 0, ',', '.') }}</strong>
            </div>
            <div class="rekap-cetak-item">
                <span>Denda Belum Lunas</span>
                <strong>Rp {{ number_format($rekapDenda['belum'], 0, ',', '.') }}</strong>
            </div>
        </div>

        <table class="laporan">
            <thead>
                <tr>
                    <th style="width: 38px;">No</th>
                    <th>Nama Anggota</th>
                    <th>Judul Buku</th>
                    <th style="width: 105px;">{{ $kolom[0] }}</th>
                    <th style="width: 105px;">{{ $kolom[1] }}</th>
                    <th style="width: 92px;">Status</th>
                    <th style="width: 92px;">Denda</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="center">{{ $row['no'] }}</td>
                        <td>{{ $row['anggota'] }}</td>
                        <td>{{ $row['buku'] }}</td>
                        <td class="center">{{ $row['tgl1'] }}</td>
                        <td class="center">{{ $row['tgl2'] }}</td>
                        <td class="center">{{ $row['status'] }}</td>
                        <td class="center">
                            @if ($row['denda'] > 0)
                                Rp {{ number_format($row['denda'], 0, ',', '.') }}
                                <span class="denda-status">{{ $row['status_bayar'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">Tidak ada data pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <footer class="footer-cetak">
            <div>
                Jumlah data: <strong>{{ number_format($rows->count(), 0, ',', '.') }}</strong>
            </div>
            <div class="kanan">
                Dicetak pada {{ $dicetakPada }}<br>
                Oleh: {{ $dicetakOleh }}
            </div>
        </footer>
    </main>
</body>
</html>

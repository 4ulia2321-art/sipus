@php
    $logoSekolahPath = public_path('images/logo-smpn1-pasirjambu.png');
    $logoSipusPath = public_path('images/logo-sipus.png');

    $logoSekolah = file_exists($logoSekolahPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoSekolahPath))
        : null;
    $logoSipus = file_exists($logoSipusPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoSipusPath))
        : null;

    $labelPeriode = $mulai->isSameDay($selesai)
        ? $mulai->translatedFormat('d F Y')
        : $mulai->translatedFormat('d F Y') . ' s.d. ' . $selesai->translatedFormat('d F Y');

    $namaPerpustakaan = \App\Support\Pengaturan::get('nama_perpustakaan', 'Perpustakaan SMP Negeri 1 Pasirjambu');
    $alamat = \App\Support\Pengaturan::get('alamat', 'Jl. Raya Pasirjambu, Kabupaten Bandung');

    $filterAktif = array_filter([
        $filter['status'] !== '' ? 'Status: ' . $filter['status'] : null,
        $filter['kelas'] !== '' ? 'Kelas: ' . $filter['kelas'] : null,
        $filter['kategori'] !== '' ? 'Kategori: ' . $filter['kategori'] : null,
        $filter['q'] !== '' ? 'Pencarian: "' . $filter['q'] . '"' : null,
    ]);

    $rupiah = fn ($nilai) => 'Rp ' . number_format((float) $nilai, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Denda - {{ $labelPeriode }}</title>
    <style>
        * { box-sizing: border-box; }

        @page {
            size: A4 portrait;
            margin: 13mm 12mm 14mm;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.35;
        }

        .kop {
            width: 100%;
            border-collapse: collapse;
        }

        .kop td {
            vertical-align: middle;
        }

        .kop-logo {
            width: 70px;
            text-align: center;
        }

        .kop-logo img {
            width: 60px;
            height: 60px;
        }

        .kop-teks {
            text-align: center;
            padding: 0 8px;
        }

        .kop-pemkab {
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .kop-dinas {
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .kop-sekolah {
            margin-top: 1px;
            font-size: 14pt;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .kop-perpus {
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .kop-alamat {
            margin-top: 2px;
            font-size: 8pt;
            color: #374151;
        }

        .garis-kop {
            margin: 7px 0 12px;
            border-top: 2px solid #111827;
            border-bottom: 0.8px solid #111827;
            height: 2px;
        }

        .judul-laporan {
            margin: 0;
            font-size: 13pt;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .periode-laporan {
            margin: 2px 0 0;
            font-size: 9pt;
            text-align: center;
            color: #374151;
        }

        .filter-info {
            margin: 6px 0 0;
            font-size: 8pt;
            text-align: center;
            color: #4b5563;
        }

        .rekap {
            width: 100%;
            margin: 12px 0 10px;
            border-collapse: collapse;
        }

        .rekap td {
            width: 25%;
            padding: 6px 8px;
            border: 0.8px solid #9ca3af;
            vertical-align: top;
        }

        .rekap .label {
            display: block;
            font-size: 7.5pt;
            font-weight: 700;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .rekap .nilai {
            display: block;
            margin-top: 2px;
            font-size: 10.5pt;
            font-weight: 700;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data th,
        table.data td {
            padding: 4px 4px;
            border: 0.7px solid #6b7280;
            vertical-align: top;
        }

        table.data th {
            background: #e5edf7;
            font-size: 7.2pt;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.01em;
        }

        table.data td {
            font-size: 7.6pt;
        }

        table.data tr {
            page-break-inside: avoid;
        }

        .c {
            text-align: center;
        }

        .r {
            text-align: right;
        }

        .nowrap {
            white-space: nowrap;
        }

        .empty {
            padding: 14px !important;
            text-align: center;
            color: #6b7280;
        }

        table.data tfoot td {
            background: #f3f4f6;
            font-weight: 700;
        }

        .status {
            font-size: 7pt;
            font-weight: 700;
        }

        .status-lunas {
            color: #047857;
        }

        .status-belum {
            color: #b91c1c;
        }

        .catatan {
            margin-top: 8px;
            font-size: 7.5pt;
            color: #4b5563;
        }

        .ttd {
            width: 100%;
            margin-top: 22px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .ttd td {
            vertical-align: top;
            font-size: 9pt;
        }

        .ttd .kanan {
            width: 42%;
            text-align: center;
        }

        .ttd .tanggal {
            margin-bottom: 2px;
        }

        .ttd .ruang {
            height: 54px;
        }

        .ttd .nama {
            font-weight: 700;
            text-decoration: underline;
        }

        .ttd .keterangan {
            font-size: 8pt;
            color: #4b5563;
        }

        .footer {
            margin-top: 12px;
            padding-top: 5px;
            border-top: 0.7px solid #9ca3af;
            font-size: 7.2pt;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if ($logoSekolah)
                    <img src="{{ $logoSekolah }}" alt="Logo SMP Negeri 1 Pasirjambu">
                @endif
            </td>
            <td class="kop-teks">
                <div class="kop-pemkab">PEMERINTAH KABUPATEN BANDUNG</div>
                <div class="kop-dinas">DINAS PENDIDIKAN</div>
                <div class="kop-sekolah">SMP NEGERI 1 PASIRJAMBU</div>
                <div class="kop-perpus">{{ $namaPerpustakaan }}</div>
                <div class="kop-alamat">{{ $alamat }}</div>
            </td>
            <td class="kop-logo">
                @if ($logoSipus)
                    <img src="{{ $logoSipus }}" alt="Logo SIPUS">
                @endif
            </td>
        </tr>
    </table>

    <div class="garis-kop"></div>

    <h1 class="judul-laporan">Laporan Denda Keterlambatan</h1>
    <p class="periode-laporan">Periode: {{ $labelPeriode }}</p>

    @if (count($filterAktif))
        <p class="filter-info">Filter aktif: {{ implode(' | ', $filterAktif) }}</p>
    @endif

    <table class="rekap">
        <tr>
            <td>
                <span class="label">Total Denda</span>
                <span class="nilai">{{ $rupiah($rekap['total_denda']) }}</span>
            </td>
            <td>
                <span class="label">Denda Lunas</span>
                <span class="nilai">{{ $rupiah($rekap['lunas']) }}</span>
            </td>
            <td>
                <span class="label">Belum Lunas</span>
                <span class="nilai">{{ $rupiah($rekap['belum']) }}</span>
            </td>
            <td>
                <span class="label">Transaksi Berdenda</span>
                <span class="nilai">{{ number_format($rekap['jumlah_transaksi'], 0, ',', '.') }} transaksi</span>
            </td>
        </tr>
    </table>

    <table class="data">
        <colgroup>
            <col style="width: 4%;">
            <col style="width: 17%;">
            <col style="width: 8%;">
            <col style="width: 21%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
            <col style="width: 7%;">
            <col style="width: 9%;">
            <col style="width: 7%;">
        </colgroup>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Anggota</th>
                <th>Kelas</th>
                <th>Judul Buku</th>
                <th>Tgl Pinjam</th>
                <th>Jatuh Tempo</th>
                <th>Tgl Kembali</th>
                <th>Hari</th>
                <th>Denda</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="c">{{ $row['no'] }}</td>
                    <td>
                        {{ $row['nama'] }}
                        <br><span style="font-size: 6.8pt; color: #4b5563;">NIS: {{ $row['nis'] }}</span>
                    </td>
                    <td class="c">{{ $row['kelas'] }}</td>
                    <td>{{ $row['buku'] }}</td>
                    <td class="c nowrap">{{ $row['tanggal_pinjam'] }}</td>
                    <td class="c nowrap">{{ $row['jatuh_tempo'] }}</td>
                    <td class="c nowrap">{{ $row['tanggal_kembali'] }}</td>
                    <td class="c">{{ $row['hari'] }}</td>
                    <td class="r nowrap">{{ $rupiah($row['denda']) }}</td>
                    <td class="c">
                        <span class="status {{ $row['status_bayar'] === 'Lunas' ? 'status-lunas' : 'status-belum' }}">
                            {{ $row['status_bayar'] }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty">Tidak ada denda pada periode dan filter yang dipilih.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="r">Total Denda</td>
                <td class="r nowrap">{{ $rupiah($rekap['total_denda']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p class="catatan">
        Tarif denda: {{ $rupiah($tarif) }} per hari keterlambatan.
        Denda dihitung otomatis dari jumlah hari keterlambatan × tarif harian.
        Pengembalian dinyatakan selesai setelah denda dilunasi.
    </p>

    <table class="ttd">
        <tr>
            <td></td>
            <td class="kanan">
                <div class="tanggal">Pasirjambu, {{ now()->translatedFormat('d F Y') }}</div>
                <div>Petugas Perpustakaan</div>
                <div class="ruang"></div>
                <div class="nama">{{ $petugas }}</div>
                <div class="keterangan">NIP. ....................................</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dicetak pada {{ $dicetakPada }} melalui SIPUS — Sistem Informasi Perpustakaan.
    </div>
</body>
</html>

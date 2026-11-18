{{-- Rekap per judul buku. Kolom tahun dan bulan dibuat otomatis dari data. --}}
<div class="card report-card rekap-judul-card">
    <div class="rekap-head">
        <h2 class="rekap-title"><i class="bi bi-book"></i> Rekap per Judul Buku</h2>
        <span class="rekap-periode">{{ $periodeLabel }}</span>
    </div>

    <p class="rekap-hint">
        <i class="bi bi-info-circle"></i>
        Angka menunjukkan jumlah salinan yang dipinjam pada bulan tersebut.
        Kolom tahun dan bulan dibuat otomatis dari data peminjaman.
    </p>

    @if ($rekapJudul->isEmpty())
        <p class="empty">Belum ada data peminjaman pada periode ini.</p>
    @else
        <div class="table-wrap">
            <table class="table report-table rekap-judul-table">
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Judul Buku</th>
                        @foreach ($kolomJudul as $tahun)
                            <th colspan="{{ count($tahun['bulan']) }}" class="rekap-tahun">
                                {{ $tahun['tahun'] }}
                            </th>
                        @endforeach
                        <th rowspan="2" class="rekap-num">Total</th>
                    </tr>
                    <tr>
                        @foreach ($kolomJudul as $tahun)
                            @foreach ($tahun['bulan'] as $bulan)
                                <th class="rekap-num">{{ $bulan['label'] }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rekapJudul as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $row['judul'] }}</td>
                            @foreach ($kolomJudul as $tahun)
                                @foreach ($tahun['bulan'] as $bulan)
                                    <td class="rekap-num">{{ $row['periode'][$bulan['key']] ?? '–' }}</td>
                                @endforeach
                            @endforeach
                            <td class="rekap-num">
                                <strong>{{ number_format($row['total_buku'], 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="rekap-hint rekap-judul-foot">
            <i class="bi bi-journal-check"></i>
            {{ number_format($rekapJudul->count(), 0, ',', '.') }} judul buku,
            {{ number_format($rekapJudul->sum('total_buku'), 0, ',', '.') }} salinan dipinjam
            pada periode ini.
        </p>
    @endif
</div>

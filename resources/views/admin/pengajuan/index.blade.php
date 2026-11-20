@extends('layouts.master')

@section('title', 'Pengajuan Peminjaman')

@push('styles')
    <style>
        /* Modal penolakan memakai komponen bawaan halaman, tanpa Bootstrap JS. */
        .tolak-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(15, 23, 42, .5);
        }
        .tolak-backdrop[hidden] { display: none; }
        .tolak-modal {
            width: 100%;
            max-width: 480px;
            padding: 20px 22px 22px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .3);
        }
        .tolak-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .tolak-head h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-size: 17px;
            color: var(--ink, #1a2f4c);
        }
        .tolak-close {
            padding: 4px;
            border: 0;
            background: transparent;
            color: #6b7a93;
            cursor: pointer;
        }
        .tolak-sub {
            margin: 8px 0 16px;
            color: #6b7a93;
            font-size: 13px;
        }
        .tolak-label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 12.5px;
            font-weight: 600;
        }
        .tolak-label span { color: #b91c1c; }
        .tolak-input {
            box-sizing: border-box;
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d7e0ec;
            border-radius: 10px;
            background: #fff;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }
        .tolak-input:focus {
            outline: none;
            border-color: var(--brand, #3a7ad4);
            box-shadow: 0 0 0 3px rgba(58, 122, 212, .15);
        }
        .tolak-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 14px;
        }
        .rejection-reason {
            margin-top: 6px;
            color: #b91c1c;
            font-size: 12px;
            line-height: 1.45;
        }
    </style>
@endpush

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

    @if ($isAdmin)
        {{-- Pengajuan dan persetujuan menjadi satu menu dengan dua tab. --}}
        <div class="loan-tabs" style="margin-bottom: 14px;">
            <a href="{{ route('pustakawan.pengajuan') }}"
               class="loan-tab {{ ! ($hanyaMenunggu ?? false) ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text"></i> Pengajuan Peminjaman
            </a>
            <a href="{{ route('pustakawan.persetujuan') }}"
               class="loan-tab {{ ($hanyaMenunggu ?? false) ? 'active' : '' }}">
                <i class="bi bi-check2-square"></i> Persetujuan Peminjaman
            </a>
        </div>
    @endif

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

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    {{-- Daftar buku tersedia untuk diajukan (khusus siswa), dipisah per kategori --}}
    @unless ($isAdmin)
        @forelse ($bukuList as $kategori => $daftarBuku)
            <section class="panel">
                <div class="panel-head">
                    <h3>
                        <i class="bi {{ $kategori === 'Cerita' ? 'bi-book-half' : 'bi-journal-bookmark' }}"></i>
                        Buku {{ $kategori }}
                    </h3>
                </div>
                <div class="catalog-grid">
                    @foreach ($daftarBuku as $buku)
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
                    @endforeach
                </div>
            </section>
        @empty
            <section class="panel">
                <div class="panel-head">
                    <h3><i class="bi bi-book"></i> Buku Tersedia untuk Dipinjam</h3>
                </div>
                <p class="empty">Belum ada buku tersedia untuk dipinjam.</p>
            </section>
        @endforelse
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
                        @if ($isAdmin)
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
                                @if ($row->status === 'Ditolak' && $row->alasan_penolakan)
                                    <div class="rejection-reason">
                                        <i class="bi bi-chat-left-text"></i>
                                        {{ $row->alasan_penolakan }}
                                    </div>
                                @endif
                            </td>
                            @if ($isAdmin)
                                <td class="table-actions">
                                    @if ($row->status === 'Menunggu')
                                        <!-- Tombol Setujui -->
                                        <form action="{{ route('pustakawan.pengajuan.approve', $row->id_peminjaman) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn-sm btn-ok"><i class="bi bi-check-lg"></i> Setujui</button>
                                        </form>

                                        <!-- Tombol Tolak -->
                                        <button type="button" class="btn-sm btn-danger"
                                                data-tolak
                                                data-id="{{ $row->id_peminjaman }}"
                                                data-action="{{ route('pustakawan.pengajuan.reject', $row->id_peminjaman) }}"
                                                data-info="{{ $row->nama_pemohon }} · {{ $row->judul }}">
                                            <i class="bi bi-x-lg"></i> Tolak
                                        </button>
                                    @else
                                        <span style="color: var(--muted);">&mdash;</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 8 : 7 }}" class="empty">
                                {{ ($hanyaMenunggu ?? false) ? 'Tidak ada pengajuan yang menunggu persetujuan.' : 'Belum ada pengajuan peminjaman.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($isAdmin)
        {{-- Modal penolakan: satu form yang dipakai ulang untuk semua baris. --}}
        <div class="tolak-backdrop" id="tolakBackdrop" hidden>
            <div class="tolak-modal" role="dialog" aria-modal="true" aria-labelledby="tolakTitle">
                <div class="tolak-head">
                    <h3 id="tolakTitle"><i class="bi bi-x-circle"></i> Tolak Pengajuan</h3>
                    <button type="button" class="tolak-close" id="tolakClose" title="Tutup">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <p class="tolak-sub" id="tolakInfo"></p>

                <form id="tolakForm" method="POST" action="">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="id_peminjaman" id="tolakId" value="{{ old('id_peminjaman') }}">

                    <label class="tolak-label" for="tolakAlasan">
                        Alasan penolakan <span>*</span>
                    </label>
                    <textarea id="tolakAlasan" name="alasan_penolakan" class="tolak-input" rows="3"
                              maxlength="500" required
                              placeholder="Contoh: Stok buku habis / syarat peminjaman tidak sesuai">{{ old('alasan_penolakan') }}</textarea>

                    <div class="tolak-actions">
                        <button type="button" class="btn-outline" id="tolakCancel">Batal</button>
                        <button type="submit" class="btn-sm btn-danger">
                            <i class="bi bi-x-lg"></i> Konfirmasi Tolak
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var backdrop = document.getElementById('tolakBackdrop');
            var form = document.getElementById('tolakForm');
            var textarea = document.getElementById('tolakAlasan');
            var info = document.getElementById('tolakInfo');
            var idInput = document.getElementById('tolakId');
            if (!backdrop || !form || !textarea) return;

            var pemicuTerakhir = null;

            function buka(button, alasan) {
                form.action = button.getAttribute('data-action');
                idInput.value = button.getAttribute('data-id') || '';
                info.textContent = button.getAttribute('data-info') || '';
                textarea.value = alasan || '';
                pemicuTerakhir = button;
                backdrop.hidden = false;
                document.body.style.overflow = 'hidden';
                textarea.focus();
            }

            function tutup() {
                backdrop.hidden = true;
                document.body.style.overflow = '';
                if (pemicuTerakhir) pemicuTerakhir.focus();
            }

            document.addEventListener('click', function (event) {
                var tombol = event.target.closest('[data-tolak]');
                if (!tombol) return;
                buka(tombol);
            });

            document.getElementById('tolakClose').addEventListener('click', tutup);
            document.getElementById('tolakCancel').addEventListener('click', tutup);
            backdrop.addEventListener('click', function (event) {
                if (event.target === backdrop) tutup();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !backdrop.hidden) tutup();
            });
            form.addEventListener('submit', function (event) {
                if (!textarea.value.trim()) {
                    event.preventDefault();
                    textarea.focus();
                }
            });

            @if ($errors->has('alasan_penolakan'))
                var idLama = @json((string) old('id_peminjaman'));
                var tombolLama = idLama
                    ? document.querySelector('[data-tolak][data-id="' + idLama + '"]')
                    : null;
                if (tombolLama) buka(tombolLama, @json(old('alasan_penolakan')));
            @endif
        })();
    </script>
@endpush

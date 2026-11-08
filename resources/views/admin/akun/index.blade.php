@extends('layouts.master')

@section('title', 'Kelola Pengguna')
@section('role', 'admin')
@section('search_placeholder', 'Cari username / nama siswa / NIS...')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/anggota.css') }}">
    <style>
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-pill.pending  { background: #fff7ed; color: #c2410c; }
        .status-pill.approved { background: #ecfdf5; color: #067647; }
        .btn-icon-warning:hover { color: #c2410c; border-color: #fdba74; }

        /* Modal Kaitkan Anggota */
        .ka-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1100;
        }
        .ka-backdrop[hidden] { display: none; }
        .ka-modal {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 16px;
            padding: 20px 22px 22px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .3);
        }
        .ka-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .ka-head h3 { margin: 0; font-size: 17px; color: var(--ink, #1a2f4c); display: flex; align-items: center; gap: 8px; }
        .ka-close { border: 0; background: transparent; font-size: 16px; color: #6b7a93; cursor: pointer; padding: 4px; }
        .ka-sub { margin: 8px 0 16px; color: #6b7a93; font-size: 13px; }
        .ka-label { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .ka-input {
            width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d7e0ec;
            border-radius: 10px; font-size: 14px; margin-bottom: 12px; background: #fff;
        }
        .ka-input:focus { outline: none; border-color: var(--brand, #3a7ad4); box-shadow: 0 0 0 3px rgba(58, 122, 212, .15); }
        .ka-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
    </style>
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <span>Kelola Pengguna</span>
    </nav>

    {{-- Page header --}}
    <div class="page-header">
        <div class="page-title">
            <i class="bi bi-person-gear"></i>
            <div>
                <h1>Kelola Pengguna</h1>
                <p>Kelola pengguna sistem beserta perannya.</p>
            </div>
        </div>
        <a href="{{ route('admin.akun.create') }}" class="btn btn-primary btn-desktop">
            <i class="bi bi-plus-lg"></i> Tambah Pengguna
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    {{-- Statistik --}}
    <div class="stats" data-live-part="stats">
        @php
            $cards = [
                ['icon' => 'bi-person-badge-fill', 'label' => 'Total Pengguna', 'value' => $stat['total'], 'sub' => 'Semua pengguna'],
                ['icon' => 'bi-shield-fill-check', 'label' => 'Admin',      'value' => $stat['admin'], 'sub' => 'Hak akses penuh'],
                ['icon' => 'bi-person-workspace',  'label' => 'Pustakawan', 'value' => $stat['pustakawan'], 'sub' => 'Petugas perpustakaan'],
                ['icon' => 'bi-person-fill',       'label' => 'Siswa',      'value' => $stat['siswa'], 'sub' => 'Akun anggota siswa'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="bi {{ $c['icon'] }}"></i></div>
                    <div>
                        <div class="stat-label">{{ $c['label'] }}</div>
                        <div class="stat-value">{{ number_format($c['value'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-sub">{{ $c['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.akun') }}" class="filters filters-anggota" data-live-form>
        <div class="field field-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari username / nama siswa / NIS...">
        </div>

        <a href="{{ route('admin.akun.create') }}" class="btn btn-primary btn-block btn-mobile">
            <i class="bi bi-plus-lg"></i> Tambah Pengguna
        </a>

        <div class="field">
            <div>
                <label>Role</label>
                <select name="role" onchange="this.form.submit()">
                    <option value="">Semua Role</option>
                    @foreach (['Admin' => 'Admin', 'Pustakawan' => 'Pustakawan', 'Siswa' => 'Siswa'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(request('role') == $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <a href="{{ route('admin.akun') }}" class="btn btn-outline btn-reset">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
        </a>
    </form>

    {{-- Tabel (desktop) --}}
    <div class="card table-card" data-live-part="list">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Terkait Anggota</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($akun as $i => $row)
                    <tr>
                        <td>{{ $akun->firstItem() + $i }}</td>
                        <td>
                            <div class="person">
                                <span class="avatar avatar-md"><i class="bi bi-person"></i></span>
                                <div>
                                    <div class="strong">{{ $row->username }}</div>
                                    @if ($row->name)
                                        <div class="muted">{{ $row->name }}</div>
                                    @endif
                                    <div class="muted">ID Pengguna #{{ $row->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="role-badge role-{{ strtolower($row->role) }}">{{ $row->role }}</span></td>
                        <td>
                            @if ($row->reset_status === 'pending')
                                <span class="status-pill pending"><i class="bi bi-hourglass-split"></i> Minta reset</span>
                            @elseif ($row->reset_status === 'approved')
                                <span class="status-pill approved"><i class="bi bi-check2-circle"></i> Dikonfirmasi</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="muted">{{ $row->nama_siswa ?? '-' }}</td>
                        <td class="muted">{{ $row->dibuat ? $row->dibuat->translatedFormat('d M Y') : '-' }}</td>
                        <td>
                            @include('admin.akun._actions', ['row' => $row])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Belum ada data akun.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="table-footer">
            <span class="muted">
                Menampilkan {{ $akun->firstItem() ?? 0 }} - {{ $akun->lastItem() ?? 0 }}
                dari {{ number_format($akun->total(), 0, ',', '.') }} data
            </span>
            {{ $akun->onEachSide(1)->links('partials.pagination') }}
        </div>
    </div>

    {{-- Kartu (mobile) --}}
    <div class="card-list" data-live-part="list">
        @foreach ($akun as $row)
            <div class="card member-card">
                <span class="avatar avatar-md"><i class="bi bi-person"></i></span>
                <div class="member-body">
                    <div class="strong">{{ $row->username }}</div>
                    @if ($row->name)
                        <div class="muted">{{ $row->name }}</div>
                    @endif
                    <div class="muted">ID Pengguna #{{ $row->id }}</div>
                    <div class="muted">{{ $row->nama_siswa ?? 'Tidak terhubung anggota' }}</div>
                    <span class="role-badge role-{{ strtolower($row->role) }}">{{ $row->role }}</span>
                    @if ($row->reset_status === 'pending')
                        <span class="status-pill pending"><i class="bi bi-hourglass-split"></i> Minta reset</span>
                    @elseif ($row->reset_status === 'approved')
                        <span class="status-pill approved"><i class="bi bi-check2-circle"></i> Dikonfirmasi</span>
                    @endif
                </div>
                <div class="member-actions">
                    @include('admin.akun._actions', ['row' => $row])
                </div>
            </div>
        @endforeach

        <div class="mobile-pagination">
            {{ $akun->onEachSide(0)->links('partials.pagination') }}
        </div>
    </div>

    {{-- Modal: Kaitkan / Ganti Anggota --}}
    <div class="ka-backdrop" id="kaBackdrop" hidden>
        <div class="ka-modal" role="dialog" aria-modal="true" aria-labelledby="kaTitle">
            <div class="ka-head">
                <h3 id="kaTitle"><i class="bi bi-link-45deg"></i> Kaitkan Anggota</h3>
                <button type="button" class="ka-close" id="kaClose" title="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <p class="ka-sub">Akun: <strong id="kaUser">—</strong> <span id="kaNow"></span></p>

            <form id="kaForm" method="POST" action="{{ route('admin.akun.link', ['id' => '__ID__']) }}">
                @csrf
                @method('PATCH')

                <label class="ka-label" for="kaInput">Cari &amp; pilih anggota</label>
                <input type="text" id="kaInput" class="ka-input" list="kaList"
                       placeholder="Ketik nama / NIS / kelas..." autocomplete="off">
                <datalist id="kaList">
                    @foreach ($anggotaList as $g)
                        <option value="{{ $g->nama_siswa }} — {{ $g->nis }} ({{ $g->kelas }}){{ $g->username ? ' • akun: '.$g->username : ' • belum ada akun' }}"
                                data-id="{{ $g->id_siswa }}" data-nama="{{ $g->nama_siswa }}" data-akun="{{ $g->username }}"></option>
                    @endforeach
                </datalist>
                <input type="hidden" name="id_siswa" id="kaValue">

                <div class="ka-actions">
                    <button type="button" class="btn btn-outline" id="kaCancel">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-link-45deg"></i> Kaitkan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var backdrop = document.getElementById('kaBackdrop');
            var form     = document.getElementById('kaForm');
            var userEl   = document.getElementById('kaUser');
            var nowEl    = document.getElementById('kaNow');
            var input    = document.getElementById('kaInput');
            var list     = document.getElementById('kaList');
            var valeur   = document.getElementById('kaValue');
            var btnClose = document.getElementById('kaClose');
            if (!backdrop || !form || !input || !list) return;

            var byLabel = {}, byId = {};
            Array.prototype.forEach.call(list.options, function (o) {
                var g = {
                    id: o.getAttribute('data-id'),
                    nama: o.getAttribute('data-nama') || '',
                    akun: o.getAttribute('data-akun') || '',
                    teks: o.value
                };
                byLabel[o.value] = g;
                if (g.id) byId[g.id] = g;
            });

            function sync() {
                var g = byLabel[input.value];
                valeur.value = g ? g.id : '';
            }

            function open(id, username, siswa, nama) {
                form.action = form.getAttribute('action').replace('__ID__', id);
                userEl.textContent = username;
                nowEl.textContent = nama ? '(saat ini: ' + nama + ')' : '(belum terkait anggota)';
                input.dataset.current = siswa || '';
                var g = byId[siswa];
                input.value = g ? g.teks : '';
                valeur.value = g ? g.id : '';
                backdrop.hidden = false;
                input.focus();
            }

            function close() { backdrop.hidden = true; }

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            input.addEventListener('click', function () {
                if (input.showPicker) { try { input.showPicker(); } catch (e) {} }
            });
            document.getElementById('kaCancel').addEventListener('click', close);
            btnClose.addEventListener('click', close);
            backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !backdrop.hidden) close();
            });

            form.addEventListener('submit', function (e) {
                var g = byLabel[input.value];
                if (!g) {
                    e.preventDefault();
                    input.focus();
                    return;
                }
                if (g.akun && String(g.id) !== String(input.dataset.current || '')) {
                    if (!confirm('Anggota "' + g.nama + '" sudah memiliki akun "' + g.akun +
                        '". Tautan akan dipindahkan ke akun ini. Lanjutkan?')) {
                        e.preventDefault();
                    }
                }
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-buka-kaitkan]');
                if (!btn) return;
                open(btn.getAttribute('data-id'), btn.getAttribute('data-username'),
                     btn.getAttribute('data-siswa'), btn.getAttribute('data-nama'));
            });
        })();
    </script>
@endpush

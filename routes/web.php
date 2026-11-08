<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PustakawanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KatalogController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\StatistikController;

// Default landing redirects to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Lupa password (sebelum login)
Route::get('/lupa-password', [AuthController::class, 'showForgotForm'])->name('password.request');
Route::post('/lupa-password', [AuthController::class, 'sendResetRequest'])->name('password.email');

// Area yang wajib login
Route::middleware(['auth', 'password.changed'])->group(function () {

    // Buat password baru (setelah admin mengonfirmasi reset password)
    Route::get('/password/baru', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password/baru', [AuthController::class, 'updatePassword'])->name('password.change.update');

    // Dashboard: mengarahkan sesuai role (Admin -> /admin, Pustakawan -> /pustakawan, Siswa -> beranda)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Katalog & detail buku (dapat dilihat semua role yang sudah login)
    Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog');
    Route::get('/katalog/kategori/{nama}', [KatalogController::class, 'showKategori'])->name('katalog.kategori');
    Route::get('/buku/{buku}', [KatalogController::class, 'show'])->name('buku.show');

    // Siswa mengajukan pengembalian / menyerahkan buku kepada pustakawan
    Route::post('/pengembalian/{buku}', [KatalogController::class, 'pengembalian'])
        ->middleware('area:siswa')
        ->name('pengembalian.store');

    // ============================================================
    // AREA SISWA (khusus role Siswa)
    // ============================================================
    Route::middleware('area:siswa')->group(function () {
        Route::get('/pengajuan', [PengajuanController::class, 'index'])->name('pengajuan');
        Route::get('/pengajuan/{buku}/create', [PengajuanController::class, 'create'])->name('pengajuan.create');
        Route::post('/pengajuan', [PengajuanController::class, 'store'])->name('pengajuan.store');
    });

    // Riwayat, notifikasi, profil, dan tentang (dipakai siswa & staf)
    Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat');
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca');
    Route::get('/profil', [ProfilController::class, 'index'])->name('profil');
    Route::get('/profil/edit', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
    Route::get('/tentang', [ProfilController::class, 'tentang'])->name('tentang');

    // ============================================================
    // AREA ADMIN (khusus role Admin)
    // ============================================================
    Route::middleware('area:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'adminDashboard'])->name('dashboard');

        // Kelola kategori buku
        Route::get('/kategori', [AdminController::class, 'kategori'])->name('kategori');
        Route::get('/kategori/create', [AdminController::class, 'kategoriCreate'])->name('kategori.create');
        Route::post('/kategori', [AdminController::class, 'kategoriStore'])->name('kategori.store');
        Route::get('/kategori/{id}/edit', [AdminController::class, 'kategoriEdit'])->name('kategori.edit');
        Route::put('/kategori/{id}', [AdminController::class, 'kategoriUpdate'])->name('kategori.update');
        Route::delete('/kategori/{id}', [AdminController::class, 'kategoriDestroy'])->name('kategori.destroy');

        // Kelola buku
        Route::get('/buku', [AdminController::class, 'buku'])->name('buku');
        Route::get('/buku/create', [AdminController::class, 'bukuCreate'])->name('buku.create');
        Route::post('/buku', [AdminController::class, 'bukuStore'])->name('buku.store');
        Route::get('/buku/{id}/edit', [AdminController::class, 'bukuEdit'])->name('buku.edit');
        Route::put('/buku/{id}', [AdminController::class, 'bukuUpdate'])->name('buku.update');
        Route::delete('/buku/{id}', [AdminController::class, 'bukuDestroy'])->name('buku.destroy');

        // Anggota
        Route::get('/anggota', [AdminController::class, 'anggota'])->name('anggota');
        Route::get('/anggota/create', [AdminController::class, 'anggotaCreate'])->name('anggota.create');
        Route::post('/anggota', [AdminController::class, 'anggotaStore'])->name('anggota.store');
        Route::get('/anggota/{id}', [AdminController::class, 'anggotaShow'])->name('anggota.show');
        Route::get('/anggota/{id}/edit', [AdminController::class, 'anggotaEdit'])->name('anggota.edit');
        Route::put('/anggota/{id}', [AdminController::class, 'anggotaUpdate'])->name('anggota.update');
        Route::delete('/anggota/{id}', [AdminController::class, 'anggotaDestroy'])->name('anggota.destroy');

        // Kelola akun (t_user)
        Route::get('/akun', [AdminController::class, 'akun'])->name('akun');
        Route::get('/akun/create', [AdminController::class, 'akunCreate'])->name('akun.create');
        Route::post('/akun', [AdminController::class, 'akunStore'])->name('akun.store');
        Route::get('/akun/{id}/edit', [AdminController::class, 'akunEdit'])->name('akun.edit');
        Route::put('/akun/{id}', [AdminController::class, 'akunUpdate'])->name('akun.update');
        Route::delete('/akun/{id}', [AdminController::class, 'akunDestroy'])->name('akun.destroy');
        Route::patch('/akun/{id}/reset-password', [AdminController::class, 'akunConfirmReset'])->name('akun.reset');
        Route::patch('/akun/{id}/kaitkan', [AdminController::class, 'akunLink'])->name('akun.link');

        // Kelola kelas
        Route::get('/kelas', [AdminController::class, 'kelas'])->name('kelas');
        Route::get('/kelas/create', [AdminController::class, 'kelasCreate'])->name('kelas.create');
        Route::post('/kelas', [AdminController::class, 'kelasStore'])->name('kelas.store');
        Route::get('/kelas/{id}/edit', [AdminController::class, 'kelasEdit'])->name('kelas.edit');
        Route::put('/kelas/{id}', [AdminController::class, 'kelasUpdate'])->name('kelas.update');
        Route::delete('/kelas/{id}', [AdminController::class, 'kelasDestroy'])->name('kelas.destroy');

        // Ketentuan denda (aturan yang dipakai saat menghitung denda)
        Route::get('/ketentuan-denda', [PengaturanController::class, 'denda'])->name('ketentuan-denda');
        Route::put('/ketentuan-denda', [PengaturanController::class, 'dendaUpdate'])->name('ketentuan-denda.update');

        // Backward compatibility: tautan lama /admin/denda diarahkan ke ketentuan denda
        Route::get('/denda', fn () => redirect()->route('admin.ketentuan-denda'))->name('denda');

        // Pengaturan sistem
        Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
        Route::put('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');

        // Statistik & laporan keseluruhan
        Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik');
        Route::get('/laporan', [AdminController::class, 'laporan'])->name('laporan');
    });

    // ============================================================
    // AREA PUSTAKAWAN (khusus role Pustakawan)
    // ============================================================
    Route::middleware('area:pustakawan')->prefix('pustakawan')->name('pustakawan.')->group(function () {
        Route::get('/', [DashboardController::class, 'pustakawanDashboard'])->name('dashboard');

        // Pengajuan peminjaman: periksa, setujui, tolak
        Route::get('/pengajuan', [PengajuanController::class, 'index'])->name('pengajuan');
        Route::get('/persetujuan', [PengajuanController::class, 'persetujuan'])->name('persetujuan');
        Route::patch('/pengajuan/{peminjaman}/approve', [PengajuanController::class, 'approve'])->name('pengajuan.approve');
        Route::patch('/pengajuan/{peminjaman}/reject', [PengajuanController::class, 'reject'])->name('pengajuan.reject');

        // Peminjaman
        Route::get('/peminjaman', [PustakawanController::class, 'peminjaman'])->name('peminjaman');
        Route::get('/peminjaman/create', [PustakawanController::class, 'create'])->name('peminjaman.create');
        Route::post('/peminjaman', [PustakawanController::class, 'store'])->name('peminjaman.store');
        Route::get('/peminjaman/{id}', [PustakawanController::class, 'show'])->name('peminjaman.show');
        Route::get('/peminjaman/{id}/edit', [PustakawanController::class, 'edit'])->name('peminjaman.edit');
        Route::put('/peminjaman/{id}', [PustakawanController::class, 'update'])->name('peminjaman.update');
        Route::post('/peminjaman/{id}/perpanjang', [PustakawanController::class, 'perpanjang'])->name('peminjaman.perpanjang');

        // Keterlambatan (daftar peminjaman yang melewati jatuh tempo)
        Route::get('/keterlambatan', [PustakawanController::class, 'keterlambatan'])->name('keterlambatan');

        // Pengembalian
        Route::get('/pengembalian', [PustakawanController::class, 'pengembalian'])->name('pengembalian');
        Route::get('/pengembalian/create', [PustakawanController::class, 'pengembalianCreate'])->name('pengembalian.create');
        Route::post('/pengembalian', [PustakawanController::class, 'pengembalianStore'])->name('pengembalian.store');
        Route::get('/pengembalian/{id}/edit', [PustakawanController::class, 'pengembalianEdit'])->name('pengembalian.edit');
        Route::put('/pengembalian/{id}', [PustakawanController::class, 'pengembalianUpdate'])->name('pengembalian.update');
        Route::get('/pengembalian/{id}', [PustakawanController::class, 'pengembalianShow'])->name('pengembalian.show');

        // Denda transaksi (transaksi & denda pengembalian)
        Route::get('/denda', [PustakawanController::class, 'denda'])->name('denda');
        Route::get('/denda/{id}/edit', [PustakawanController::class, 'dendaEdit'])->name('denda.edit');
        Route::put('/denda/{id}', [PustakawanController::class, 'dendaUpdate'])->name('denda.update');
        Route::patch('/denda/{id}/lunas', [PustakawanController::class, 'dendaToggle'])->name('denda.lunas');

        // Laporan peminjaman & pengembalian (khusus Pustakawan)
        Route::get('/laporan', [PustakawanController::class, 'laporan'])->name('laporan');
    });
});

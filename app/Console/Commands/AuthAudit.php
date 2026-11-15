<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuthAudit extends Command
{
    protected $signature = 'sipus:auth-audit
                            {--username= : Periksa satu akun berdasarkan username}
                            {--password= : Password yang diuji dengan Hash::check (tidak disimpan)}';

    protected $description = 'Audit read-only autentikasi SIPUS: model, tabel t_user, koneksi, role, dan hash password';

    /**
     * Jalankan audit tanpa mengubah data, password, atau struktur database.
     */
    public function handle(): int
    {
        $semuaOk = true;

        $this->components->info('AUDIT AUTENTIKASI SIPUS');
        $this->line('Waktu        : ' . now()->toDateTimeString());
        $this->line('Environment  : ' . app()->environment());
        $this->line('Config cache : ' . (app()->configurationIsCached() ? 'aktif' : 'tidak aktif'));
        $this->newLine();

        // ------------------------------------------------------------------
        // 1. Model dan provider autentikasi
        // ------------------------------------------------------------------
        $this->components->info('1. Model dan provider autentikasi');

        $modelClass = (string) config('auth.providers.users.model');
        $driverProvider = (string) config('auth.providers.users.driver');

        try {
            $model = new $modelClass();
        } catch (Throwable $e) {
            $this->components->error('Model auth tidak dapat dibuat: ' . $e->getMessage());

            return self::FAILURE;
        }

        $modelBenar = $modelClass === User::class
            && $model->getTable() === 't_user'
            && $model->getKeyName() === 'id_user'
            && $model->usesTimestamps() === false;

        $this->table(
            ['Pemeriksaan', 'Nilai', 'Status'],
            [
                ['Provider driver', $driverProvider, $driverProvider === 'eloquent' ? 'OK' : 'SALAH'],
                ['Model auth', $modelClass, $modelClass === User::class ? 'OK' : 'SALAH'],
                ['Tabel model', $model->getTable(), $model->getTable() === 't_user' ? 'OK' : 'SALAH'],
                ['Primary key', $model->getKeyName(), $model->getKeyName() === 'id_user' ? 'OK' : 'SALAH'],
                ['Timestamps', $model->usesTimestamps() ? 'aktif' : 'nonaktif', $model->usesTimestamps() ? 'SALAH' : 'OK'],
            ]
        );

        if (! $modelBenar || $driverProvider !== 'eloquent') {
            $semuaOk = false;
        }

        // ------------------------------------------------------------------
        // 2. Koneksi database yang benar-benar dipakai
        // ------------------------------------------------------------------
        $this->components->info('2. Koneksi database');

        $namaKoneksi = (string) config('database.default');
        $konfigurasi = (array) config("database.connections.{$namaKoneksi}", []);

        $driver = $konfigurasi['driver'] ?? null;
        $host = $konfigurasi['host'] ?? null;
        $port = $konfigurasi['port'] ?? null;
        $database = $konfigurasi['database'] ?? null;

        // Jangan tampilkan URL lengkap karena biasanya memuat username/password.
        $url = $konfigurasi['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $driver = parse_url($url, PHP_URL_SCHEME) ?: $driver;
            $host = parse_url($url, PHP_URL_HOST) ?: $host;
            $port = parse_url($url, PHP_URL_PORT) ?: $port;
            $path = parse_url($url, PHP_URL_PATH);
            $database = $path !== null ? ltrim($path, '/') : $database;
        }

        $this->table(
            ['Pemeriksaan', 'Nilai'],
            [
                ['Default connection', $namaKoneksi],
                ['Driver', (string) $driver],
                ['Host', (string) ($host ?? '-')],
                ['Port', (string) ($port ?? '-')],
                ['Database', (string) ($database ?? '-')],
                ['URL koneksi', is_string($url) && $url !== '' ? 'dipakai (kredensial disembunyikan)' : 'tidak ada'],
            ]
        );

        try {
            DB::connection()->getPdo();
            $this->line('  Koneksi PDO: <fg=green>OK</>');
        } catch (Throwable $e) {
            $pesan = preg_replace('/password=[^;\s]+/i', 'password=***', $e->getMessage());
            $this->line('  Koneksi PDO: <fg=red>GAGAL</>');
            $this->components->error('Tidak dapat terhubung ke database: ' . $pesan);

            return self::FAILURE;
        }

        // ------------------------------------------------------------------
        // 3. Tabel dan kolom t_user
        // ------------------------------------------------------------------
        $this->components->info('3. Struktur tabel t_user');

        try {
            $tabelAda = Schema::hasTable('t_user');
        } catch (Throwable $e) {
            $this->components->error('Gagal memeriksa tabel: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $tabelAda) {
            $this->components->error('Tabel t_user TIDAK ADA pada koneksi ' . $namaKoneksi . '.');
            $this->line('  Ini penyebab login selalu gagal: aplikasi membaca database yang salah atau migrasi belum dijalankan.');
            $this->line('  Jangan jalankan seeder production. Cukup arahkan DATABASE_URL/DB_CONNECTION ke database SIPUS yang benar.');

            return self::FAILURE;
        }

        $kolom = Schema::getColumnListing('t_user');
        $wajib = ['id_user', 'username', 'password', 'role'];
        $hilang = array_values(array_diff($wajib, $kolom));

        $this->line('  Kolom ditemukan: ' . implode(', ', $kolom));

        if ($hilang !== []) {
            $this->components->error('Kolom wajib hilang: ' . implode(', ', $hilang));
            $semuaOk = false;
        } else {
            $this->line('  Kolom wajib: <fg=green>lengkap</>');
        }

        if (! in_array('name', $kolom, true)) {
            $this->components->warn('Kolom name tidak ada. Login masih bisa berjalan, tetapi nama tampilan memakai fallback username.');
        }

        foreach (['reset_requested_at', 'reset_approved_at'] as $kolomReset) {
            if (! in_array($kolomReset, $kolom, true)) {
                $this->components->warn("Kolom {$kolomReset} tidak ada. Fitur reset password tidak akan berjalan penuh.");
            }
        }

        // ------------------------------------------------------------------
        // 4. Jumlah akun dan role
        // ------------------------------------------------------------------
        $this->components->info('4. Akun dan role');

        try {
            $total = (int) DB::table('t_user')->count();
            $this->line("  Total akun: {$total}");

            if (in_array('role', $kolom, true)) {
                $perRole = DB::table('t_user')
                    ->select('role', DB::raw('COUNT(*) as total'))
                    ->groupBy('role')
                    ->orderBy('role')
                    ->get();

                $barisRole = [];
                foreach ($perRole as $role) {
                    $barisRole[] = [
                        $role->role === null || $role->role === '' ? '(kosong)' : $role->role,
                        (string) $role->total,
                    ];
                }

                if ($barisRole === []) {
                    $this->components->warn('Tabel t_user kosong. Tidak ada akun yang bisa login.');
                    $semuaOk = false;
                } else {
                    $this->table(['Role', 'Jumlah'], $barisRole);
                }
            }

            if (in_array('username', $kolom, true) && in_array('role', $kolom, true)) {
                $staf = DB::table('t_user')
                    ->whereIn('role', ['Admin', 'Pustakawan'])
                    ->orderBy('role')
                    ->orderBy('username')
                    ->get(['id_user', 'username', 'name', 'role']);

                $barisStaf = [];
                foreach ($staf as $akun) {
                    $barisStaf[] = [
                        (string) $akun->id_user,
                        (string) $akun->username,
                        (string) ($akun->name ?? '-'),
                        (string) $akun->role,
                    ];
                }

                if ($barisStaf === []) {
                    $this->components->warn('Tidak ada akun Admin/Pustakawan pada database ini.');
                    $semuaOk = false;
                } else {
                    $this->table(['ID', 'Username', 'Nama', 'Role'], $barisStaf);
                }
            }
        } catch (Throwable $e) {
            $this->components->error('Gagal membaca akun: ' . $e->getMessage());
            $semuaOk = false;
        }

        // ------------------------------------------------------------------
        // 5. Algoritma hash password
        // ------------------------------------------------------------------
        $this->components->info('5. Hash password');

        if (! in_array('password', $kolom, true)) {
            $this->components->error('Kolom password tidak ada, verifikasi Hash::check tidak mungkin dilakukan.');
            $semuaOk = false;
        } else {
            try {
                $sebaranHash = [];
                $akunTanpaHash = [];

                foreach (DB::table('t_user')->select('id_user', 'username', 'password')->orderBy('id_user')->cursor() as $akun) {
                    $info = password_get_info((string) $akun->password);
                    $algo = $info['algoName'] ?? 'unknown';
                    $sebaranHash[$algo] = ($sebaranHash[$algo] ?? 0) + 1;

                    if ($algo === 'unknown' && count($akunTanpaHash) < 10) {
                        $akunTanpaHash[] = $akun->username;
                    }
                }

                $barisHash = [];
                foreach ($sebaranHash as $algo => $jumlah) {
                    $barisHash[] = [
                        $algo,
                        (string) $jumlah,
                        in_array($algo, ['bcrypt', 'argon2i', 'argon2id'], true) ? 'OK' : 'PERIKSA',
                    ];
                }

                $this->table(['Algoritma', 'Jumlah', 'Status'], $barisHash);

                if (isset($sebaranHash['unknown'])) {
                    $this->components->warn(
                        'Ada password yang bukan hash (plaintext/tidak dikenal): ' . implode(', ', $akunTanpaHash)
                    );
                    $this->line('  Akun tersebut tidak bisa login lewat Hash::check. Perbaiki lewat fitur reset password, bukan dengan mengganti data manual.');
                    $semuaOk = false;
                }
            } catch (Throwable $e) {
                $this->components->error('Gagal membaca hash password: ' . $e->getMessage());
                $semuaOk = false;
            }
        }

        // ------------------------------------------------------------------
        // 6. Uji akun tertentu (opsional)
        // ------------------------------------------------------------------
        $username = trim((string) $this->option('username'));
        $password = (string) $this->option('password');

        if ($username !== '' && $password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Password untuk diuji (Enter untuk lewati)');
        }

        if ($username !== '') {
            $this->components->info('6. Uji akun: ' . $username);

            try {
                $user = User::findByUsername($username);
            } catch (Throwable $e) {
                $this->components->error('Gagal mencari akun: ' . $e->getMessage());
                $user = null;
            }

            if (! $user) {
                $this->components->error('Username tidak ditemukan pada tabel t_user di database yang sedang dipakai.');
                $this->line('  Jika akun ini ada di localhost tetapi tidak di sini, production membaca database lain.');
                $semuaOk = false;
            } else {
                $info = password_get_info((string) $user->password);
                $this->table(
                    ['Pemeriksaan', 'Nilai'],
                    [
                        ['ID', (string) $user->id_user],
                        ['Username', (string) $user->username],
                        ['Nama', (string) ($user->name ?? '-')],
                        ['Role', (string) ($user->role ?? '-')],
                        ['Hash password', (string) ($info['algoName'] ?? 'unknown')],
                    ]
                );

                if (in_array($user->role, ['Admin', 'Siswa', 'Pustakawan'], true)) {
                    $this->line('  Role valid: <fg=green>OK</>');
                } else {
                    $this->components->warn('Role tidak valid: ' . (string) $user->role);
                    $semuaOk = false;
                }

                if ($password !== '') {
                    if (Hash::check($password, (string) $user->password)) {
                        $this->line('  Hash::check: <fg=green>COCOK</>');
                    } else {
                        $this->components->error('Hash::check: TIDAK COCOK. Password production berbeda atau hash rusak.');
                        $semuaOk = false;
                    }
                } else {
                    $this->components->warn('Uji password dilewati. Jalankan dengan --password=... atau biarkan interaktif.');
                }
            }
        }

        // ------------------------------------------------------------------
        // 7. Session dan cache
        // ------------------------------------------------------------------
        $this->components->info('7. Session dan cache');

        $pemeriksaanPendukung = [];

        if (config('session.driver') === 'database') {
            $tabelSession = (string) config('session.table', 'sessions');
            $ada = false;

            try {
                $ada = Schema::hasTable($tabelSession);
            } catch (Throwable) {
                $ada = false;
            }

            $pemeriksaanPendukung[] = ['Session database', $tabelSession, $ada ? 'OK' : 'TABEL HILANG'];

            if (! $ada) {
                $semuaOk = false;
            }
        } else {
            $pemeriksaanPendukung[] = ['Session driver', (string) config('session.driver'), 'OK'];
        }

        if (config('cache.default') === 'database') {
            $tabelCache = (string) config('cache.stores.database.table', 'cache');
            $ada = false;

            try {
                $ada = Schema::hasTable($tabelCache);
            } catch (Throwable) {
                $ada = false;
            }

            $pemeriksaanPendukung[] = ['Cache database', $tabelCache, $ada ? 'OK' : 'TABEL HILANG'];

            if (! $ada) {
                $semuaOk = false;
            }
        } else {
            $pemeriksaanPendukung[] = ['Cache store', (string) config('cache.default'), 'OK'];
        }

        $this->table(['Pemeriksaan', 'Nilai', 'Status'], $pemeriksaanPendukung);

        // ------------------------------------------------------------------
        // 8. Kesimpulan
        // ------------------------------------------------------------------
        $this->newLine();

        if ($semuaOk) {
            $this->components->info('Hasil: konfigurasi autentikasi dasar OK.');
        } else {
            $this->components->error('Hasil: ditemukan masalah pada autentikasi. Baca baris SALAH/GAGAL/PERIKSA di atas.');
        }

        $this->line('Catatan: setelah mengubah .env production, jalankan "php artisan config:clear" lalu "php artisan config:cache".');
        $this->line('Jangan menjalankan migrate:fresh, db:seed, atau mengganti password untuk diagnosis.');

        return $semuaOk ? self::SUCCESS : self::FAILURE;
    }
}

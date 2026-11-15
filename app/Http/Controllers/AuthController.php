<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Password sementara yang dipakai saat pengguna mengajukan reset password.
     * Setelah admin mengonfirmasi, pengguna wajib menggantinya dengan password baru.
     */
    public const DEFAULT_PASSWORD = 'sipus2026';

    /**
     * Show the application's login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Login SIPUS hanya memakai kolom username pada tabel t_user.
        // Email dan NIS tidak dipakai sebagai identitas login.
        $username = trim($credentials['username']);
        $user = User::findByUsername($username);

        if (! $user) {
            $this->catatLoginGagal($request, $username, null, false, 'username_tidak_ditemukan');

            return back()
                ->withInput($request->only('username'))
                ->with('error', 'Username atau password yang Anda masukkan salah.');
        }

        // Verifikasi password bcrypt dengan Hash::check. Password yang sudah
        // tersimpan tidak diubah dan tidak di-hash ulang saat login.
        if (! Hash::check($credentials['password'], $user->password)) {
            $this->catatLoginGagal($request, $username, $user, false, 'password_tidak_cocok');

            return back()
                ->withInput($request->only('username'))
                ->with('error', 'Username atau password yang Anda masukkan salah.');
        }

        if (! in_array($user->role, ['Admin', 'Siswa', 'Pustakawan'], true)) {
            $this->catatLoginGagal($request, $username, $user, true, 'role_tidak_valid');

            return back()
                ->withInput($request->only('username'))
                ->with('error', 'Akun ini tidak memiliki role yang valid. Hubungi administrator.');
        }

        // Tabel t_user production tidak memiliki kolom remember_token,
        // sehingga login sesi tidak memakai fitur "remember me".
        Auth::login($user, false);
        $request->session()->regenerate();

        Log::info('Login SIPUS berhasil', [
            'username'      => $user->username,
            'role'          => $user->role,
            'auth_model'    => config('auth.providers.users.model'),
            'db_connection' => config('database.default'),
        ]);

        // Jika admin sudah mengonfirmasi reset password, paksa pengguna
        // membuat password baru sebelum bisa memakai aplikasi.
        if (! empty($user->reset_approved_at)) {
            return redirect()->route('password.change')
                ->with('status', 'Admin telah mengonfirmasi reset password. Silakan buat password baru Anda.');
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Tampilkan form "Lupa Password".
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Terima permintaan lupa password.
     *
     * Alur: pengguna memasukkan email/username/NIS yang lupa password.
     * Sistem menyetel password ke DEFAULT_PASSWORD sebagai password sementara,
     * lalu menandai permintaan agar muncul di menu Kelola Pengguna untuk dikonfirmasi admin.
     */
    public function sendResetRequest(Request $request)
    {
        $data = $request->validate([
            'identitas' => ['required', 'string'],
        ]);

        $identitas = trim($data['identitas']);

        $user = DB::table('t_user')->where('username', $identitas)->first();

        // Jika bukan username, coba cari lewat NIS anggota.
        if (! $user) {
            $user = DB::table('t_user as u')
                ->join('siswa as s', 's.id_user', '=', 'u.id_user')
                ->where('s.nis', $identitas)
                ->select('u.id_user', 'u.username')
                ->first();
        }

        if (! $user) {
            return back()->withInput()->withErrors([
                'identitas' => 'Email/NIS tidak terdaftar pada sistem.',
            ]);
        }

        // Set password sementara dan tandai permintaan reset.
        // Kolom reset_approved_at dibiarkan apa adanya: admin yang akan mengonfirmasi.
        DB::table('t_user')->where('id_user', $user->id_user)->update([
            'password'           => Hash::make(self::DEFAULT_PASSWORD),
            'reset_requested_at' => now(),
        ]);

        return redirect()->route('login')->with(
            'status',
            'Permintaan reset password terkirim. Sementara ini gunakan password default "'
            . self::DEFAULT_PASSWORD
            . '". Setelah admin mengonfirmasi, Anda akan diminta membuat password baru.'
        );
    }

    /**
     * Tampilkan halaman "Buat Password Baru".
     * Hanya bisa diakses pengguna yang sudah dikonfirmasi oleh admin.
     */
    public function showChangePassword()
    {
        if (empty(Auth::user()->reset_approved_at)) {
            return redirect()->route('dashboard');
        }

        return view('auth.change-password');
    }

    /**
     * Simpan password baru pengguna dan bersihkan status reset.
     * Setelah ini password default tidak berlaku lagi.
     */
    public function updatePassword(Request $request)
    {
        if (empty(Auth::user()->reset_approved_at)) {
            return redirect()->route('dashboard')->with('info', 'Tidak ada permintaan reset password yang aktif.');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        DB::table('t_user')->where('id_user', Auth::id())->update([
            'password'           => Hash::make($data['password']),
            'reset_requested_at' => null,
            'reset_approved_at'  => null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password baru berhasil disimpan.');
    }

    /**
     * Catat kegagalan login tanpa pernah menyimpan password atau hash-nya.
     *
     * Log ini dipakai untuk membedakan tiga penyebab utama di production:
     * username tidak ada di database yang sedang dipakai, hash password tidak
     * cocok, atau role tidak valid. Informasi koneksi membantu mendeteksi
     * aplikasi yang ternyata membaca SQLite kosong, bukan MySQL/DATABASE_URL.
     */
    private function catatLoginGagal(
        Request $request,
        string $username,
        ?User $user,
        bool $passwordValid,
        string $alasan
    ): void {
        try {
            $koneksi = (string) config('database.default');
            $konfigurasi = (array) config("database.connections.{$koneksi}", []);

            $driver = $konfigurasi['driver'] ?? null;
            $host = $konfigurasi['host'] ?? null;
            $port = $konfigurasi['port'] ?? null;
            $database = $konfigurasi['database'] ?? null;

            // URL koneksi dapat berisi kredensial. Hanya ambil host/port/nama
            // database, jangan pernah menulis URL lengkap ke log.
            $url = $konfigurasi['url'] ?? null;
            if (is_string($url) && $url !== '') {
                $driver = parse_url($url, PHP_URL_SCHEME) ?: $driver;
                $host = parse_url($url, PHP_URL_HOST) ?: $host;
                $port = parse_url($url, PHP_URL_PORT) ?: $port;
                $path = parse_url($url, PHP_URL_PATH);
                $database = $path !== null ? ltrim($path, '/') : $database;
            }

            $hashInfo = $user && is_string($user->password) && $user->password !== ''
                ? password_get_info($user->password)
                : [];

            Log::warning('Login SIPUS gagal', [
                'username'       => $username,
                'alasan'         => $alasan,
                'user_ditemukan' => $user !== null,
                'password_valid' => $passwordValid,
                'role'           => $user?->role,
                'hash_algo'      => $hashInfo['algoName'] ?? null,
                'auth_model'     => config('auth.providers.users.model'),
                'auth_table'     => (new User())->getTable(),
                'db_connection'  => $koneksi,
                'db_driver'      => $driver,
                'db_host'        => $host,
                'db_port'        => $port,
                'db_database'    => $database,
                'config_cached'  => app()->configurationIsCached(),
                'app_env'        => app()->environment(),
                'ip'             => $request->ip(),
            ]);
        } catch (\Throwable) {
            // Kegagalan logging tidak boleh mengubah respons login.
        }
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}

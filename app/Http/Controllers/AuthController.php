<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        $username = $request->username;

        // Izinkan siswa login memakai NIS sebagai pengganti username
        if (! str_contains($username, '@')) {
            $dariNis = DB::table('t_user')
                ->join('siswa', 'siswa.id_user', '=', 't_user.id_user')
                ->where('siswa.nis', $username)
                ->value('t_user.username');

            if ($dariNis) {
                $username = $dariNis;
            }
        }

        if (Auth::attempt(['username' => $username, 'password' => $request->password], $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Jika admin sudah mengonfirmasi reset password, paksa pengguna
            // membuat password baru sebelum bisa memakai aplikasi.
            if (! empty(Auth::user()->reset_approved_at)) {
                return redirect()->route('password.change')
                    ->with('status', 'Admin telah mengonfirmasi reset password. Silakan buat password baru Anda.');
            }

            return redirect()->intended('/dashboard');
        }

        // If credentials don't match, return back with an error
        return back()->withInput($request->only('username'))->with('error', 'NIS / Username atau password yang Anda masukkan salah.');
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

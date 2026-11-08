@extends('layouts.auth')

@section('title', 'Buat Password Baru')

@section('content')
    <section class="auth-card">
        <div class="mail-icon">
            <span class="spark s1"></span><span class="spark s2"></span><span class="spark s3"></span>
            <span class="spark s4"></span><span class="spark s5"></span><span class="spark s6"></span>
            <i class="bi bi-shield-lock-fill mail"></i>
            <span class="lock"><i class="bi bi-key-fill"></i></span>
        </div>

        <h2>Buat Password Baru</h2>
        <p class="lead">
            Permintaan reset password Anda telah dikonfirmasi oleh admin.
            Silakan buat kata sandi baru untuk melanjutkan.
        </p>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}" novalidate>
            @csrf

            <div class="field-wrap">
                <label for="password" class="field-label">Password Baru</label>
                <div class="field has-eye @error('password') has-error @enderror">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password"
                           placeholder="Masukkan password baru" autocomplete="new-password" autofocus required>
                    <button type="button" class="toggle-eye" data-target="password" aria-label="Lihat password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                @error('password')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="field-wrap">
                <label for="password_confirmation" class="field-label">Konfirmasi Password Baru</label>
                <div class="field has-eye">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           placeholder="Ulangi password baru" autocomplete="new-password" required>
                    <button type="button" class="toggle-eye" data-target="password_confirmation" aria-label="Lihat password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-check-lg"></i> Simpan Password Baru
            </button>
        </form>

        <p class="auth-note">
            Gunakan minimal <strong>6 karakter</strong>. Password default tidak akan bisa dipakai lagi setelah ini.
        </p>
    </section>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.toggle-eye').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) return;

            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';

            var icon = btn.querySelector('i');
            if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';

            btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Lihat password');
        });
    });
</script>
@endpush

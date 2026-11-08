@extends('layouts.auth')

@section('title', 'Lupa Password')

@section('content')
    <section class="auth-card">
        {{-- Ikon amplop + gembok --}}
        <div class="mail-icon">
            <span class="spark s1"></span><span class="spark s2"></span><span class="spark s3"></span>
            <span class="spark s4"></span><span class="spark s5"></span><span class="spark s6"></span>
            <i class="bi bi-envelope-fill mail"></i>
            <span class="lock"><i class="bi bi-lock-fill"></i></span>
        </div>

        <h2>Lupa Password</h2>
        <p class="lead">
            Masukkan email atau NIS Anda yang terdaftar pada sistem SIPUS.
            Anda akan mendapatkan password sementara untuk masuk, lalu diminta
            membuat password baru setelah admin mengonfirmasi.
        </p>

        @if (session('status'))
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <label for="identitas" class="field-label">Email atau NIS</label>
            <div class="field @error('identitas') has-error @enderror">
                <i class="bi bi-envelope"></i>
                <input type="text" id="identitas" name="identitas" value="{{ old('identitas') }}"
                       placeholder="Masukkan email atau NIS Anda" autocomplete="username" autofocus required>
            </div>
            @error('identitas')
                <small class="field-error">{{ $message }}</small>
            @enderror

            <button type="submit" class="btn-submit">
                <i class="bi bi-send-fill"></i> Kirim Permintaan Reset
            </button>
        </form>

        <hr class="divider">

        <a href="{{ Route::has('login') ? route('login') : '#' }}" class="back-link">
            <i class="bi bi-arrow-left"></i> Kembali ke Login
        </a>
    </section>
@endsection

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Logout tidak memerlukan CSRF: mencegah "419 Page Expired" saat token
        // di halaman sudah kedaluwarsa (sesi dibuat ulang / halaman lama terbuka).
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);

        // Pembatas area: "area:admin" (khusus Admin) dan "area:pustakawan" (khusus Pustakawan).
        // "password.changed": paksa pengguna membuat password baru bila admin sudah konfirmasi reset.
        $middleware->alias([
            'area'             => \App\Http\Middleware\EnsureArea::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);

        // Selalu minta browser mengambil versi terbaru halaman (tidak menahan
        // tampilan lama di cache/back-forward cache).
        $middleware->web(append: [
            \App\Http\Middleware\PreventStalePages::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

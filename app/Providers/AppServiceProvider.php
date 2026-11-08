<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Menentukan area/URL aktif ("admin" atau "pustakawan") agar view
        // bersama (peminjaman, pengembalian, anggota, denda, laporan) dapat
        // membangun tautan sesuai area pengguna yang login.
        View::composer('*', function ($view) {
            $role = auth()->user()->role ?? null;

            $view->with('area', $role === 'Pustakawan' ? 'pustakawan' : 'admin');
        });
    }
}

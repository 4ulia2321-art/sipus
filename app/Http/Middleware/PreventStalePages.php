<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencegah browser menyimpan versi lama halaman web.
 *
 * Halaman SIPUS menampilkan data yang berubah (nama siswa, status pengajuan,
 * denda, dan sebagainya). Tanpa header ini, tab yang sudah lama terbuka bisa
 * terus menampilkan data lama sampai pengguna melakukan hard refresh.
 */
class PreventStalePages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Pada Laravel, "headers" adalah properti (ResponseHeaderBag), bukan method.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureArea
{
    /**
     * Membatasi akses ke area tertentu sesuai role.
     * Contoh: area:admin, area:pustakawan, area:siswa, area:admin,pustakawan.
     *
     * Role yang tidak sesuai diarahkan ke dashboard miliknya sendiri.
     */
    public function handle(Request $request, Closure $next, string ...$areas): Response
    {
        $role = $request->user()?->role;

        if ($areas === []) {
            return $next($request);
        }

        $petaArea = [
            'admin'      => ['Admin'],
            'pustakawan' => ['Pustakawan'],
            'siswa'      => ['Siswa'],
        ];

        foreach ($areas as $area) {
            if (in_array($role, $petaArea[$area] ?? [], true)) {
                return $next($request);
            }
        }

        return $this->redirectFor($role);
    }

    private function redirectFor(?string $role): Response
    {
        return match ($role) {
            'Admin'      => redirect()->route('admin.dashboard'),
            'Pustakawan' => redirect()->route('pustakawan.dashboard'),
            'Siswa'      => redirect()->route('dashboard'),
            default      => redirect()->route('login'),
        };
    }
}

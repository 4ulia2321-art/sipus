<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaca/pengatur nilai pengaturan aplikasi (tabel `pengaturan`).
 * Dipakai agar ketentuan denda dan lama pinjam tidak lagi hardcode.
 */
class Pengaturan
{
    private static ?array $cache = null;

    public static function get(string $kunci, mixed $default = null): mixed
    {
        $semua = self::semua();

        return array_key_exists($kunci, $semua) && $semua[$kunci] !== null
            ? $semua[$kunci]
            : $default;
    }

    public static function int(string $kunci, int $default = 0): int
    {
        return (int) self::get($kunci, $default);
    }

    /**
     * @return array<string, string|null>
     */
    public static function semua(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (! Schema::hasTable('pengaturan')) {
            return self::$cache = [];
        }

        return self::$cache = DB::table('pengaturan')->pluck('nilai', 'kunci')->all();
    }

    public static function set(string $kunci, mixed $nilai): void
    {
        DB::table('pengaturan')->updateOrInsert(
            ['kunci' => $kunci],
            [
                'nilai'      => $nilai === null ? null : (string) $nilai,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        self::$cache = null;
    }

    public static function lupakanCache(): void
    {
        self::$cache = null;
    }
}

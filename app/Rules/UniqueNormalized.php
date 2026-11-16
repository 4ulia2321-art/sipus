<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Validasi nilai unik dengan membandingkan LOWER(TRIM(kolom)).
 *
 * Dipakai agar "Buku A", " buku a ", dan "BUKU A" dianggap sebagai nilai
 * yang sama, baik di MySQL production maupun SQLite saat pengujian.
 */
class UniqueNormalized implements ValidationRule
{
    public function __construct(
        private string $table,
        private string $column,
        private int|string|null $ignoreId = null,
        private ?string $message = null,
        private string $keyColumn = 'id',
    ) {
        foreach ([$table, $column, $keyColumn] as $identifier) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                throw new InvalidArgumentException('Nama tabel/kolom tidak valid.');
            }
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $query = DB::table($this->table)
            ->whereRaw("LOWER(TRIM({$this->column})) = ?", [mb_strtolower(trim($value))]);

        if ($this->ignoreId !== null) {
            $query->where($this->keyColumn, '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail($this->message ?? "Nilai {$attribute} sudah digunakan.");
        }
    }
}

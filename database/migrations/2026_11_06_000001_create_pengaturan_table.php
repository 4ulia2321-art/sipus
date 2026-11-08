<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pengaturan bersifat aditif: hanya menambah tabel baru untuk
     * menyimpan ketentuan denda dan pengaturan sistem yang dapat diubah Admin.
     * Tabel yang sudah ada tidak diubah.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pengaturan')) {
            Schema::create('pengaturan', function (Blueprint $table) {
                $table->increments('id_pengaturan');
                $table->string('kunci', 100)->unique();
                $table->text('nilai')->nullable();
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            ['kunci' => 'denda_per_hari', 'nilai' => '500', 'keterangan' => 'Nominal denda keterlambatan per hari (Rupiah)'],
            ['kunci' => 'lama_pinjam_hari', 'nilai' => '7', 'keterangan' => 'Lama masa peminjaman (hari)'],
            ['kunci' => 'nama_perpustakaan', 'nilai' => 'Perpustakaan SMP Negeri 01 Pasirjambu', 'keterangan' => 'Nama perpustakaan'],
            ['kunci' => 'alamat', 'nilai' => 'Jl. Raya Pasirjambu, Kabupaten Bandung', 'keterangan' => 'Alamat perpustakaan'],
            ['kunci' => 'no_telp', 'nilai' => '-', 'keterangan' => 'Nomor telepon perpustakaan'],
            ['kunci' => 'jam_operasional', 'nilai' => 'Senin - Jumat, 07.00 - 15.00 WIB', 'keterangan' => 'Jam layanan perpustakaan'],
            ['kunci' => 'kepala_perpustakaan', 'nilai' => '-', 'keterangan' => 'Nama kepala perpustakaan'],
        ];

        foreach ($defaults as $item) {
            $sudahAda = DB::table('pengaturan')->where('kunci', $item['kunci'])->exists();

            if (! $sudahAda) {
                DB::table('pengaturan')->insert(array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // Sinkronisasi data lama: peminjaman yang sudah punya catatan
        // pengembalian seharusnya berstatus "Dikembalikan". Perbaikan ini
        // hanya menyelaraskan data transaksi, bukan mengubah struktur tabel.
        DB::table('peminjaman')
            ->where('status', 'Dipinjam')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pengembalian')
                    ->whereColumn('pengembalian.id_peminjaman', 'peminjaman.id_peminjaman');
            })
            ->update(['status' => 'Dikembalikan']);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};

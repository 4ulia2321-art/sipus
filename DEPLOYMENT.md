# Deployment SIPUS (EthioDeploy)

Aplikasi ini memakai Laravel 12 dan database MySQL dengan tabel pengguna
`t_user` (`id_user`, `username`, `name`, `password`, `role`). Login hanya
memakai `username`, bukan email atau NIS.

## Environment wajib

Minimal isi variabel berikut di dashboard EthioDeploy:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # hasil php artisan key:generate --show
APP_URL=https://domain-anda

# Cara 1 (disarankan): satu URL koneksi
DATABASE_URL=mysql://user:password@host:3306/dbsipus

# Cara 2: variabel terpisah
# DB_CONNECTION=mysql
# DB_HOST=host
# DB_PORT=3306
# DB_DATABASE=dbsipus
# DB_USERNAME=user
# DB_PASSWORD=password
```

`config/database.php` otomatis memilih driver dari skema `DATABASE_URL`
apabila `DB_CONNECTION` kosong. Tetap disarankan mengisi `DB_CONNECTION=mysql`
secara eksplisit agar tidak bergantung pada inferensi.

Jangan mengisi `AUTH_MODEL`. Provider autentikasi sudah dikunci ke
`App\Models\User` dan tidak lagi dapat ditimpa environment.

## Setelah deploy

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika tabel belum ada, jalankan migrasi biasa:

```bash
php artisan migrate --force
```

Jangan menjalankan `migrate:fresh` atau `db:seed` di production. Seeder
`ContohDataSeeder` bersifat destruktif dan dapat menghapus data.

## Error "Table 't_user' already exists" (kode 1050)

Kondisi ini terjadi bila tabel `t_user` sudah diimpor ke database production
sebelum migrasi dicatat di tabel `migrations`. Migrasi `t_user` sekarang
idempoten: tabel dan kolom hanya dibuat jika belum ada, sedangkan data akun
yang sudah ada tidak dihapus atau diubah.

Langkah aman:

1. Buat backup database production.
2. Periksa koneksi dan struktur tanpa mengubah data:

   ```bash
   php artisan sipus:auth-audit
   ```

   Pastikan database yang terbaca benar dan jumlah akun tetap 1.024.
3. Jalankan migrasi biasa:

   ```bash
   php artisan config:clear
   php artisan migrate --force
   php artisan config:cache
   ```

Jangan menghapus tabel `t_user`, menjalankan `migrate:fresh`, `db:seed`, atau
mengubah password pengguna untuk mengatasi error ini.

## Audit login read-only

Command ini tidak mengubah data, password, atau struktur database:

```bash
php artisan sipus:auth-audit
php artisan sipus:auth-audit --username=admin67@sipus.sch.id
```

Untuk menguji password, biarkan command meminta password secara interaktif
(lebih aman daripada menulis password di riwayat shell). Command akan
menampilkan:

- model auth, tabel, primary key, dan status config cache;
- koneksi database yang benar-benar dipakai (kredensial disembunyikan);
- keberadaan tabel/kolom `t_user`;
- jumlah akun per role serta daftar akun Admin/Pustakawan;
- algoritma hash password (bcrypt/argon2/plaintext);
- hasil `Hash::check` untuk akun yang diuji;
- tabel session dan cache yang dibutuhkan.

## Arti pesan "Username atau password salah"

| Temuan audit | Arti |
|---|---|
| `Default connection: sqlite` | Production salah membaca SQLite, bukan MySQL. Perbaiki `DATABASE_URL`/`DB_CONNECTION`. |
| `Tabel t_user TIDAK ADA` | Database target salah atau migrasi belum dijalankan. |
| `Total akun: 0` | Database benar tetapi belum berisi akun. Jangan seed production; impor data yang benar. |
| `Username tidak ditemukan` | Akun tidak ada di database yang sedang dipakai. |
| `Hash::check: TIDAK COCOK` | Password production berbeda atau hash rusak. |
| `Hash password: unknown` | Password tersimpan sebagai plaintext, bukan hash. |
| `Config cache: aktif` | Setelah mengubah environment, jalankan `php artisan config:cache` ulang. |

Log kegagalan login juga ditulis ke `storage/logs/laravel.log` tanpa password
atau hash, berisi username, status akun ditemukan, validitas password, role,
model auth, serta koneksi/database yang dipakai.

# Catatan Perubahan

## 0.3.0 — 2026-10-22

- Menambahkan halaman dokumentasi `/docs` dengan daftar isi dan contoh penggunaan framework.
- Memperbarui halaman welcome dan dokumentasi dengan logo TajdidCore serta palet teal, biru, dan navy yang responsif.

- Menambahkan header keamanan pada seluruh respons dinamis, CSP tanpa inline script/style, pemeriksaan header, dan batas body request.
- Memperbaiki middleware global untuk membungkus 404/405 serta error action; memprioritaskan rute HEAD eksplisit.
- Menambahkan validasi dengan respons 422 dan exception HTTP terstruktur.
- Menambahkan session lazy dengan cookie aman, timeout idle/absolut, middleware CSRF, autentikasi berbasis UserProvider, dan policy Gate.
- Menambahkan rate limit per IP berbasis file lock tanpa mempercayai forwarding header.
- Membagikan satu koneksi PDO per Application, menambahkan savepoint untuk transaksi bersarang dan pagination berbatas.
- Menambahkan migrasi forward-only yang atomik untuk SQLite/PostgreSQL dengan pencatatan checksum dan CLI migrate.
- Menambahkan grup rute, nama rute, URL generator, dan parameter variadic eksplisit pada container.
- Menambahkan logger JSON yang bisa diganti tanpa mencatat pesan exception atau argumen sensitif secara default.
- Menambahkan pengujian komponen, database, serta HTTP/session nyata di CLI dan CI tanpa dependency runtime eksternal.

Perubahan perilaku: rute method/path identik ditolak; middleware global juga dijalankan untuk rute yang tidak ditemukan; CSP memerlukan stylesheet/script terpisah atau konfigurasi CSP aplikasi; session memakai cookie Secure secara default. Sesuaikan aplikasi sebelum memperbarui.

## 0.2.0 — 2026-10-22

- Menaikkan versi minimum PHP dari 8.2 ke 8.4.
- Menambahkan pemeriksaan versi PHP pada bootstrap untuk penggunaan tanpa Composer.
- Menambahkan CI untuk PHP 8.4 dan 8.5 dengan driver SQLite.
- Menjadikan warning dan deprecation sebagai kegagalan pengujian.
- Memperbarui dokumentasi persyaratan dan nomor versi pada CLI serta halaman awal.

Pembaruan ini membutuhkan PHP 8.4 atau lebih baru. Pengguna PHP 8.2/8.3 perlu memperbarui runtime sebelum menggunakan versi ini.

## 0.1.0

- Rilis awal dengan routing, container, middleware, request/response, view PHP, konfigurasi, database PDO, dan CLI.

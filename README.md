# TajdidCore

**TajdidCore** adalah framework pengembangan aplikasi berbasis PHP yang dirancang dengan semangat *Tajdid* (pembaruan), terinspirasi dari nilai-nilai Islam Berkemajuan yang menjadi landasan gerakan Muhammadiyah.

TajdidCore hadir sebagai fondasi teknologi yang modern, fleksibel, aman, dan terstruktur untuk mendukung pengembangan berbagai aplikasi berbasis web, khususnya dalam ekosistem pendidikan, organisasi, kesehatan, sosial, dan layanan digital Muhammadiyah.

Dengan mengedepankan prinsip efisiensi, keterbukaan, skalabilitas, serta kemudahan integrasi antarsistem, TajdidCore bertujuan membantu pengembang membangun solusi digital yang berkelanjutan dan memberikan manfaat luas bagi masyarakat.

**TajdidCore — Building Technology, Inspiring Progress.**

TajdidCore adalah fondasi framework PHP 8.4+ untuk membangun aplikasi web. Versi **0.3.0** menyediakan routing, dependency injection, middleware, request/response, view PHP, konfigurasi, database PDO, dan CLI. Versi ini menambahkan validasi, session, CSRF, autentikasi yang bisa diintegrasikan dengan tabel pengguna aplikasi, otorisasi, rate limit, migrasi, serta pagination tanpa dependency runtime eksternal.

Terinspirasi kenyamanan framework seperti Laravel, TajdidCore memakai action invokable dan komposisi objek yang eksplisit: tidak memiliki facade global, Artisan, Blade, atau Eloquent. Framework dan aplikasi contoh berada dalam satu proyek agar mudah dipelajari dan dikembangkan.

## Mulai

Gunakan PHP 8.4 atau lebih baru dengan ekstensi JSON dan PDO. Untuk database SQLite bawaan, aktifkan juga `pdo_sqlite`. Fitur session/autentikasi browser membutuhkan `ext-session`. Composer dan bootstrap akan menolak PHP di bawah 8.4.

```sh
php tajdid serve
```

Buka http://127.0.0.1:8000. Dokumentasi framework tersedia di http://127.0.0.1:8000/docs, atau melalui tombol **Baca dokumentasi** pada halaman awal. Tidak perlu menginstal dependency untuk menjalankan proyek. Composer bersifat opsional:

```sh
composer install
composer test
```

```sh
php tajdid routes
php tajdid make:action ShowArticle
php tajdid test
```

Server bawaan PHP ditujukan untuk pengembangan. Untuk deployment, arahkan document root ke `public/` dan seluruh URL aplikasi yang bukan berkas statis ke `public/index.php` melalui konfigurasi Nginx/Apache.

## Struktur

| Lokasi | Fungsi |
| --- | --- |
| `src/` | Komponen inti namespace `Tajdid\Core` |
| `app/Actions/` | Handler aplikasi |
| `app/Middleware/` | Middleware aplikasi |
| `routes/web.php` | Registrasi rute |
| `config/` | Konfigurasi PHP |
| `views/` | Template PHP |
| `bootstrap/app.php` | Komposisi aplikasi dan service binding |
| `public/` | Document root |
| `storage/` | Database dan berkas aplikasi |
| `tests/run.php` | Pengujian tanpa library eksternal |

## Routing dan action

Tambahkan rute di callback `routes/web.php`:

```php
$routes->get('/articles/{slug}', App\Actions\ShowArticle::class);
$routes->post('/articles', App\Actions\StoreArticle::class);
$routes->add('DELETE', '/articles/{id}', App\Actions\DeleteArticle::class);
```

Parameter rute diberikan berdasarkan nama. Gunakan `string` untuk ID dari URL; lakukan validasi dan konversi di action. Dependensi objek pada constructor dan method action di-resolve otomatis:

```php
namespace App\Actions;

use Tajdid\Core\Http\Request;
use Tajdid\Core\Http\Response;

final class ShowArticle
{
    public function __invoke(Request $request, string $slug): Response
    {
        return Response::json(['slug' => $slug, 'preview' => $request->input('preview')]);
    }
}
```

Action bisa mengembalikan `Response`, array (JSON), atau string (HTML). Router menyediakan 404, 405 dengan header Allow, dan fallback HEAD ke GET. Path cocok secara persis, termasuk trailing slash. Placeholder mencocokkan satu segmen URL. POST menerima form atau JSON; JSON tidak valid menghasilkan 400.

## Middleware dan container

Middleware mengimplementasikan `Tajdid\Core\Http\Middleware` dan method `process(Request $request, Closure $next): Response`.

```php
$routes->through(App\Middleware\SecurityHeaders::class); // termasuk respons 404/405
$routes->get('/private', App\Actions\PrivatePage::class)
    ->through(Tajdid\Core\Http\Middleware\RequireAuth::class); // setelah binding UserProvider
```

Middleware masuk sesuai urutan registrasi, lalu keluar dengan urutan terbalik. Middleware global membungkus pencarian rute, termasuk 404/405 dan error action/middleware rute. Exception dari middleware global ditangani oleh Application; middleware global yang melempar exception tidak melanjutkan pipeline. Header keamanan inti tetap diterapkan oleh Application pada seluruh respons dinamis, termasuk JSON rusak dan error middleware global.

Binding interface atau singleton dilakukan di `bootstrap/app.php` sebelum rute digunakan:

```php
$app->container->bind(MyService::class, fn ($container) => new MyService(), shared: true);
```

Container mendukung constructor injection, method injection, binding factory, shared instance, dan deteksi circular dependency. Variadic menerima array parameter eksplisit. Union/intersection type memerlukan binding factory eksplisit. Jangan mendaftarkan service yang menyimpan request/user sebagai singleton ketika membuat worker jangka panjang; lifecycle worker belum didukung.

## View dan konfigurasi

```php
return $view->render('articles/show', ['title' => $title]);
```

Template di `views/articles/show.php` memakai `<?= \Tajdid\Core\View::escape($title) ?>` untuk output pengguna. Template PHP tidak melakukan escaping otomatis.

Konfigurasi dibaca melalui `$config->get('app.name')`. Variabel lingkungan yang didukung: `APP_NAME`, `APP_DEBUG`, `DB_DSN`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_SECURE`. Set variabel ini pada shell/server; proyek belum memuat berkas `.env` secara otomatis. Debug secara default mati; exception dicatat sebagai JSON melalui `Log\Logger` ke PHP `error_log`. Log error bawaan hanya menyimpan kelas exception dan lokasi kode, tanpa pesan exception, argumen, atau body request. Bind implementasi `Log\Logger` sendiri untuk backend log lain.

```sh
APP_DEBUG=true php tajdid serve 8080
```

## Database

Inject `Tajdid\Core\Database\Connection` ke action. Default menggunakan SQLite di `storage/database/app.sqlite`; pasang driver `pdo_sqlite`. Untuk MySQL/PostgreSQL, set DSN dan pasang driver PDO yang sesuai.

```php
$rows = $db->select('SELECT * FROM articles WHERE slug = ?', [$slug]);
$db->execute('INSERT INTO articles (title) VALUES (?)', [$title]);
$db->transaction(function ($db) {
    $db->execute('UPDATE accounts SET balance = balance - ? WHERE id = ?', [100, 1]);
    $db->execute('UPDATE accounts SET balance = balance + ? WHERE id = ?', [100, 2]);
});
```

Gunakan binding untuk nilai input; identifier tabel/kolom harus berasal dari kode tepercaya. Koneksi bersifat lazy dan shared per Application. Transaksi bersarang menggunakan savepoint (SQLite, MySQL, PostgreSQL); gunakan mesin tabel yang mendukung transaksi dan hindari DDL yang melakukan implicit commit.

```php
$page = $db->paginate('SELECT id, title FROM articles ORDER BY id', [], page: 1, perPage: 20);
```

Pagination mengembalikan `data`, `total`, `page`, `per_page`, dan `last_page`; maksimal 100 baris per halaman. SQL harus berupa SELECT tepercaya tanpa semicolon penutup.

Buat berkas berurutan seperti `database/migrations/001_create_articles.php`:

```php
<?php
return static function (Tajdid\Core\Database\Connection $db): void {
    $db->execute('CREATE TABLE articles (id INTEGER PRIMARY KEY, title VARCHAR(255) NOT NULL)');
};
```

Jalankan `php tajdid migrate`. Migrasi bersifat forward-only, tercatat dengan checksum, dan dijalankan dalam transaksi per berkas. Jangan mengedit migrasi yang sudah diterapkan. Migrator saat ini mendukung SQLite/PostgreSQL; MySQL tetap didukung untuk query dan transaksi biasa. Jalankan migrasi dari satu proses deployment, bukan beberapa proses bersamaan. ORM, query builder, queue, cache, dan rollback migrasi belum disediakan.

## Pemeriksaan

CI menjalankan pengujian dan pemeriksaan sintaks pada PHP 8.4 dan 8.5 dengan `pdo_sqlite`. Pengujian CI mengubah warning dan deprecation PHP menjadi kegagalan agar masalah kompatibilitas terdeteksi.

```sh
php tajdid test
find src app bootstrap config routes views public tests database -name '*.php' -print0 | xargs -0 -n 1 php -l
curl http://127.0.0.1:8000/health
curl -X POST http://127.0.0.1:8000/api/echo -H 'Content-Type: application/json' -d '{"name":"Bayu"}'
```

## Validasi dan error HTTP

```php
$data = $validator->validate($request->all(), [
    'email' => 'required|string|email|max:254',
    'name' => 'required|string|min:2|max:100',
    'role' => 'required|in:reader,editor',
]);
```

Inject `Validation\Validator`. Hasil hanya memuat field yang memiliki aturan; nilai tidak dikonversi otomatis. Aturan tersedia: `required`, `nullable`, `string`, `integer`, `email`, `min`, `max`, `in`. `min/max` mengukur panjang string dalam byte, bukan nilai numerik. Kegagalan menghasilkan JSON 422 dengan `errors` per field. Aturan yang salah merupakan error konfigurasi.

Lempar `Http\HttpException(403, 'Forbidden')` untuk error HTTP yang pesannya aman dilihat pengguna. Exception lain menghasilkan 500 dengan detail tersembunyi kecuali `APP_DEBUG=true`. Seluruh respons error memakai `Cache-Control: no-store`.

## Session, CSRF, autentikasi, dan otorisasi

Session dibuka hanya ketika dipakai. Default cookie: Secure, HttpOnly, SameSite=Lax, tanpa domain cookie; strict mode menolak ID session yang tidak terdaftar. Timeout idle 30 menit dan absolut 8 jam ditegakkan ketika session dibuka, terpisah dari garbage collection PHP. Konfigurasi ada di `config/security.php`. Untuk pengembangan melalui HTTP localhost saja:

```sh
SESSION_SECURE=false php tajdid serve
```

Autentikasi tidak membuat tabel pengguna atau rute login secara otomatis. Implementasikan `Security\UserProvider` pada aplikasi dan bind interface tersebut di `bootstrap/app.php`:

```php
$app->container->bind(Tajdid\Core\Security\UserProvider::class,
    fn ($c) => $c->get(App\Security\DatabaseUsers::class));
```

`findByCredentials()` menerima identitas seperti email dan mengembalikan array dengan `id` serta hash `password`, atau null. `findById()` mengembalikan user terbaru dari penyimpanan, atau null jika user sudah tidak boleh mengakses. Gunakan query berparameter, pilih hanya field yang aman, simpan hash menggunakan `password_hash()`, dan verifikasi status akun pada provider. Jangan simpan password mentah. Untuk bcrypt batasi password maksimal 72 byte; `Auth` menolak input melebihi batas itu agar tidak menerima password yang terpotong. Hash Argon2 menerima maksimal 4096 byte pada `Auth`.

Contoh registrasi rute setelah provider tersedia:

```php
use Tajdid\Core\Http\{Request, HttpException};
use Tajdid\Core\Http\Middleware\{RateLimit, VerifyCsrf, RequireAuth};
use Tajdid\Core\Security\{Session, Auth};
use Tajdid\Core\Validation\Validator;

$routes->get('/csrf-token', fn (Session $session) => ['token' => $session->token()]);
$routes->post('/login', function (Request $request, Validator $validator, Auth $auth): array {
    $data = $validator->validate($request->body(), [
        'email' => 'required|string|email|max:254',
        'password' => 'required|string|max:72',
    ]);
    if (!$auth->attempt(['email' => $data['email']], $data['password'])) {
        throw new HttpException(401, 'Invalid credentials');
    }
    return ['ok' => true];
})->through(RateLimit::class, VerifyCsrf::class);
$routes->get('/profile', fn (Auth $auth) => ['user' => $auth->user()])
    ->through(RequireAuth::class);
$routes->post('/logout', function (Auth $auth): array {
    $auth->logout();
    return ['ok' => true];
})->through(VerifyCsrf::class);
```

Kirim token melalui header `X-CSRF-Token` atau field body `_token`; token pada query URL ditolak. Pasang `VerifyCsrf` pada seluruh rute browser yang mengubah data, termasuk login/logout. Endpoint API tanpa cookie autentikasi dapat memakai mekanisme token aplikasi sendiri. Jangan memakai GET/HEAD/OPTIONS untuk perubahan data. Session login dan logout merotasi ID serta token CSRF; klien perlu mengambil token baru setelah login. `Auth::user()` menghapus field `password` dari hasil. Rehash password, reset password, MFA, dan token API adalah tanggung jawab aplikasi. Nama session berawalan `_` dicadangkan framework.

Untuk otorisasi, daftarkan policy pada instance `Security\Gate` di bootstrap:

```php
$app->container->get(Tajdid\Core\Security\Gate::class)
    ->define('articles.edit', fn (?array $user, array $article): bool =>
        $user !== null && (string) $article['author_id'] === (string) $user['id']);
```

Inject `Gate` ke action dan panggil `$gate->authorize('articles.edit', $auth->user(), $article)` sebelum mengubah data. Ability yang belum terdaftar ditolak; policy harus mengembalikan boolean true untuk mengizinkan akses.

Pengaturan session mengikuti [panduan keamanan session PHP](https://www.php.net/manual/en/session.security.ini.php); password memakai [verifikasi hash bawaan PHP](https://www.php.net/manual/en/function.password-verify.php).

## Routing tambahan

```php
$routes->group('/admin', [Tajdid\Core\Http\Middleware\RequireAuth::class], function ($routes): void {
    $routes->get('/articles/{id}', App\Actions\ShowArticle::class)->name('articles.show');
});
// Inject Router untuk menghasilkan URL:
$url = $router->url('articles.show', ['id' => '123'], ['preview' => '1']);
```

Grup mendukung nesting dan prefix, nama rute harus unik saat digunakan untuk URL, dan parameter/query URL di-encode. Registrasi method/path identik ditolak. Rute HEAD eksplisit diprioritaskan atas fallback GET. Constraint parameter dilakukan melalui validasi di action.

## Keamanan HTTP dan deployment

Header keamanan dinamis bawaan mencakup nosniff, Referrer-Policy, X-Frame-Options=DENY, dan CSP yang membatasi sumber ke origin sendiri serta menolak inline script/style. CSS halaman awal ada di `public/app.css`. Sesuaikan `security.content_security_policy` secara eksplisit jika aplikasi membutuhkan sumber lain. Semua nilai header diperiksa untuk mencegah injeksi karakter kontrol. Header berkas statis perlu diatur pada web server.

Body dibatasi 1 MiB melalui Content-Length, dan stream JSON dibaca maksimal batas + 1 byte. JSON rusak/scalar menghasilkan 400; request terlalu besar menghasilkan 413. Atur juga batas body Nginx/Apache serta `post_max_size`/`upload_max_filesize` PHP, karena form/upload dapat diproses PHP sebelum framework berjalan. File upload belum memiliki API validasi tersendiri.

Rute contoh `/api/echo` memakai `RateLimit`: default 60 request per 60 detik per IP pada seluruh rute yang memakai middleware itu. Bucket file dilindungi lock dan disimpan di `storage/rate-limits/`. Identitas berasal dari REMOTE_ADDR; header forwarding diabaikan untuk mencegah spoofing. Di balik reverse proxy, atur alamat klien di web server hanya dari proxy tepercaya. Penyimpanan ini ditujukan untuk satu host; gunakan implementasi limiter bersama untuk beberapa server. Bucket kedaluwarsa direset saat dipakai kembali; jadwalkan pembersihan berkas lama saat layanan berhenti agar tidak berlomba dengan lock aktif.

Gunakan HTTPS, `APP_DEBUG=false`, document root `public/`, dan batasi izin tulis ke direktori storage serta penyimpanan session PHP di luar public. Framework belum menyediakan lifecycle worker persisten, CORS, pengelolaan secret, atau audit keamanan independen. Tidak ada dependency runtime pihak ketiga yang ditambahkan.

Pengujian HTTP (`php tests/http.php`) menjalankan server sementara pada localhost dan menghapus session serta bucket uji setelah selesai. `php tajdid test` dan `composer test` menjalankan pengujian komponen sekaligus HTTP. Pengujian database lokal dilewati jika `pdo_sqlite` tidak tersedia; CI memasangnya agar migrasi, savepoint, dan pagination tetap diuji.

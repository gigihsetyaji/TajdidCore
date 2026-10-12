# TajdidCore

**TajdidCore** adalah framework pengembangan aplikasi berbasis PHP yang dirancang dengan semangat *Tajdid* (pembaruan), terinspirasi dari nilai-nilai Islam Berkemajuan yang menjadi landasan gerakan Muhammadiyah.

TajdidCore hadir sebagai fondasi teknologi yang modern, fleksibel, aman, dan terstruktur untuk mendukung pengembangan berbagai aplikasi berbasis web, khususnya dalam ekosistem pendidikan, organisasi, kesehatan, sosial, dan layanan digital Muhammadiyah.

Dengan mengedepankan prinsip efisiensi, keterbukaan, skalabilitas, serta kemudahan integrasi antarsistem, TajdidCore bertujuan membantu pengembang membangun solusi digital yang berkelanjutan dan memberikan manfaat luas bagi masyarakat.

**TajdidCore — Building Technology, Inspiring Progress.**

TajdidCore adalah fondasi framework PHP 8.2+ untuk membangun aplikasi web. Versi awal **0.1.0** menyediakan routing, dependency injection, middleware, request/response, view PHP, konfigurasi, database PDO, dan CLI.

Terinspirasi kenyamanan framework seperti Laravel, TajdidCore memakai action invokable dan komposisi objek yang eksplisit: tidak memiliki facade global, Artisan, Blade, atau Eloquent. Framework dan aplikasi contoh berada dalam satu proyek agar mudah dipelajari dan dikembangkan.

## Mulai

```sh
php tajdid serve
```

Buka http://127.0.0.1:8000. Tidak perlu menginstal dependency untuk menjalankan proyek. Composer bersifat opsional:

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
$routes->through(App\Middleware\SecurityHeaders::class); // semua rute yang cocok
$routes->get('/private', App\Actions\PrivatePage::class)
    ->through(App\Middleware\RequireLogin::class); // setelah Anda membuat middleware ini
```

Middleware masuk sesuai urutan registrasi, lalu keluar dengan urutan terbalik. Respons 404/405 dan error dibuat di luar pipeline middleware.

Binding interface atau singleton dilakukan di `bootstrap/app.php` sebelum rute digunakan:

```php
$app->container->bind(MyService::class, fn ($container) => new MyService(), shared: true);
```

Container mendukung constructor injection, method injection, binding factory, shared instance, dan deteksi circular dependency. Union type dan parameter variadic belum didukung.

## View dan konfigurasi

```php
return $view->render('articles/show', ['title' => $title]);
```

Template di `views/articles/show.php` memakai `<?= \Tajdid\Core\View::escape($title) ?>` untuk output pengguna. Template PHP tidak melakukan escaping otomatis.

Konfigurasi dibaca melalui `$config->get('app.name')`. Variabel lingkungan yang didukung: `APP_NAME`, `APP_DEBUG`, `DB_DSN`, `DB_USERNAME`, `DB_PASSWORD`. Set variabel ini pada shell/server; proyek belum memuat berkas `.env` secara otomatis. Debug secara default mati; exception dicatat melalui PHP `error_log`.

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

Gunakan binding untuk nilai input. Transaksi bersarang belum didukung. Schema/migrasi, ORM, session, CSRF, autentikasi, validasi, queue, dan cache belum termasuk versi ini; siapkan komponen tersebut sebelum memakai framework untuk aplikasi produksi yang membutuhkannya.

## Pemeriksaan

```sh
php tajdid test
find src app bootstrap config routes views public tests -name '*.php' -exec php -l {} \;
curl http://127.0.0.1:8000/health
curl -X POST http://127.0.0.1:8000/api/echo -H 'Content-Type: application/json' -d '{"name":"Bayu"}'
```

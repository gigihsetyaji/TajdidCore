<?php
use Tajdid\Core\View;
$topics = [
    'mulai' => 'Mulai cepat', 'struktur' => 'Struktur proyek', 'routing' => 'Routing',
    'action' => 'Action & HTTP', 'container' => 'Container & middleware',
    'view' => 'View & konfigurasi', 'validasi' => 'Validasi input', 'database' => 'Database',
    'migrasi' => 'Migrasi', 'session' => 'Session & CSRF', 'auth' => 'Autentikasi & otorisasi',
    'keamanan' => 'Keamanan & rate limit', 'cli' => 'CLI & pengujian', 'deployment' => 'Deployment',
];
$code = static function (string $source): void { ?>
    <pre tabindex="0"><code><?= View::escape($source) ?></code></pre>
<?php };
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Dokumentasi TajdidCore 0.3.0: mulai cepat, routing, database, validasi, session, autentikasi, dan deployment.">
    <title>Dokumentasi · <?= View::escape($name) ?></title>
    <meta name="theme-color" content="#005477">
    <link rel="icon" type="image/png" href="/images/tajdidcore-logo.png">
    <link rel="stylesheet" href="/app.css">
</head>
<body class="docs-page">
<a class="skip-link" href="#konten">Langsung ke dokumentasi</a>
<header class="docs-header">
    <a class="brand" href="/" aria-label="Kembali ke halaman awal">
        <img class="brand-logo" src="/images/tajdidcore-logo.png" alt="" width="64" height="64">
        <span><?= View::escape($name) ?></span>
    </a>
    <span class="version">v0.3.0 · PHP 8.4+</span>
    <a href="/">← Beranda</a>
</header>
<div class="docs-layout">
    <aside class="docs-sidebar">
        <nav aria-label="Daftar isi dokumentasi">
            <strong>PANDUAN PENGEMBANG</strong>
            <?php foreach ($topics as $id => $label): ?>
                <a href="#<?= View::escape($id) ?>"><?= View::escape($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="docs-content" id="konten">
        <small>DOKUMENTASI / 0.3.0</small>
        <h1>Dari ide ke aplikasi.</h1>
        <p class="lead">Panduan membangun dengan <?= View::escape($name) ?>. Komponen kecil, dependensi eksplisit, dan PHP yang mudah ditelusuri.</p>
        <div class="notice">PHP 8.4+ · Tanpa dependency runtime pihak ketiga · Composer opsional</div>

        <section id="mulai">
            <h2>Mulai cepat</h2>
            <p>Jalankan perintah berikut dari direktori proyek. Pastikan ekstensi JSON dan PDO aktif; gunakan <code>pdo_sqlite</code> untuk database bawaan dan <code>session</code> untuk autentikasi browser.</p>
            <?php $code("php tajdid serve\n\n# Server tersedia di http://127.0.0.1:8000\n# Gunakan port lain bila diperlukan:\nphp tajdid serve 8080"); ?>
            <p>Composer tersedia sebagai pilihan untuk autoload dan perintah pengujian:</p>
            <?php $code("composer install\ncomposer test"); ?>
            <p>Coba <a href="/health">/health</a> untuk respons status atau <a href="/hello/Bayu">/hello/Bayu</a> untuk parameter URL.</p>
        </section>

        <section id="struktur">
            <h2>Struktur proyek</h2>
            <div class="table-scroll"><table><thead><tr><th>Direktori</th><th>Tanggung jawab</th></tr></thead><tbody>
                <?php foreach ([
                    'app/Actions/' => 'Action dan logika aplikasi', 'src/' => 'Komponen inti Tajdid\\Core',
                    'routes/web.php' => 'Registrasi rute dan middleware', 'bootstrap/app.php' => 'Komposisi aplikasi dan binding service',
                    'config/' => 'Konfigurasi PHP', 'views/' => 'Template halaman PHP', 'public/' => 'Document root dan aset publik',
                    'database/migrations/' => 'Migrasi database berurutan', 'storage/' => 'Database dan data runtime', 'tests/' => 'Pengujian komponen dan HTTP',
                ] as $path => $description): ?>
                    <tr><td><code><?= View::escape($path) ?></code></td><td><?= View::escape($description) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <p>Alur request: <code>public/index.php</code> → bootstrap → Application → middleware global → router → middleware rute → action → response.</p>
        </section>

        <section id="routing">
            <h2>Routing</h2>
            <p>Tambahkan rute di dalam callback <code>routes/web.php</code>. Placeholder URL diinjeksikan ke parameter action berdasarkan namanya.</p>
            <?php $code(<<<'CODE'
$routes->get('/articles/{slug}', App\Actions\ShowArticle::class);
$routes->post('/articles', App\Actions\StoreArticle::class);
$routes->add('DELETE', '/articles/{id}', App\Actions\DeleteArticle::class);

$routes->get('/hello/{name}', fn (string $name) => [
    'message' => "Halo, $name!",
])->name('hello');

// Inject Tajdid\Core\Routing\Router untuk membuat URL.
$url = $router->url('hello', ['name' => 'Bayu Putra'], ['preview' => '1']);
CODE); ?>
            <p>Path cocok secara persis, termasuk trailing slash. Gunakan parameter URL bertipe <code>string</code>, lalu validasi sebelum menggunakannya. Method/path yang sama tidak boleh didaftarkan dua kali.</p>
            <?php $code(<<<'CODE'
$routes->group('/admin', [Tajdid\Core\Http\Middleware\RequireAuth::class],
    function (Tajdid\Core\Routing\Router $routes): void {
        $routes->get('/articles', App\Actions\ListArticles::class);
    }
);
CODE); ?>
            <p>Nama rute harus unik saat dipakai untuk URL. Router menyediakan 404, 405 dengan header <code>Allow</code>, serta fallback HEAD ke GET; rute HEAD eksplisit diprioritaskan.</p>
        </section>

        <section id="action">
            <h2>Action & HTTP</h2>
            <p>Buat handler kecil yang fokus pada satu tindakan. Nama class dari CLI harus diawali huruf besar.</p>
            <?php $code('php tajdid make:action ShowArticle'); ?>
            <?php $code(<<<'CODE'
<?php
namespace App\Actions;

use Tajdid\Core\Http\Request;
use Tajdid\Core\Http\Response;

final class ShowArticle
{
    public function __invoke(Request $request, string $slug): Response
    {
        return Response::json([
            'slug' => $slug,
            'preview' => $request->query('preview', '0'),
        ]);
    }
}
CODE); ?>
            <p>Action dapat mengembalikan <code>Response</code>, array untuk JSON, atau string untuk HTML. <code>input()</code> menggabungkan body dan query dengan prioritas body; <code>body()</code> dan <code>query()</code> memisahkannya. Tanpa argumen, keduanya mengembalikan seluruh field sumber tersebut.</p>
            <?php $code(<<<'CODE'
$email = $request->body('email');
$agent = $request->header('User-Agent');
return Response::redirect('/docs');

// Untuk error yang aman ditampilkan ke pengguna:
throw new Tajdid\Core\Http\HttpException(403, 'Forbidden');
CODE); ?>
        </section>

        <section id="container">
            <h2>Container & middleware</h2>
            <p>Dependensi objek pada constructor dan method action di-resolve otomatis. Bind interface atau shared service di <code>bootstrap/app.php</code> sebelum <code>return $app</code>.</p>
            <?php $code(<<<'CODE'
$app->container->bind(App\Contracts\Mailer::class,
    fn ($container) => new App\Services\SmtpMailer(),
    shared: true,
);
CODE); ?>
            <p>Contoh class di atas perlu Anda buat di aplikasi. Container mendeteksi circular dependency. Union/intersection type memerlukan factory eksplisit; variadic menerima array parameter eksplisit.</p>
            <?php $code(<<<'CODE'
<?php
namespace App\Middleware;

use Closure;
use Tajdid\Core\Http\{Middleware, Request, Response};

final class NoCache implements Middleware
{
    public function process(Request $request, Closure $next): Response
    {
        return $next($request)->withHeader('Cache-Control', 'no-store');
    }
}

// Di callback routes/web.php:
$routes->through(App\Middleware\NoCache::class); // Global
// Atau per rute:
$routes->get('/private', App\Actions\PrivatePage::class)
    ->through(App\Middleware\NoCache::class);
CODE); ?>
            <p>Urutan masuk mengikuti registrasi; urutan keluar terbalik. Middleware global membungkus 404/405 dan error action/middleware rute. Exception middleware global ditangani Application, dan header keamanan inti tetap diterapkan.</p>
        </section>

        <section id="view">
            <h2>View & konfigurasi</h2>
            <p>Inject <code>Tajdid\Core\View</code> lalu render template dari <code>views/</code>. Escaping output pengguna dilakukan secara eksplisit.</p>
            <?php $code(<<<'CODE'
return $view->render('articles/show', ['title' => $title]);

// views/articles/show.php:
<h1><?= \Tajdid\Core\View::escape($title) ?></h1>
CODE); ?>
            <p>Inject <code>Tajdid\Core\Config</code> untuk membaca <code>$config->get('app.name')</code>. Variabel lingkungan didukung: <code>APP_NAME</code>, <code>APP_DEBUG</code>, <code>DB_DSN</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>, dan <code>SESSION_SECURE</code>.</p>
            <div class="notice">Berkas <code>.env</code> belum dimuat otomatis. Set variabel pada shell atau konfigurasi server. Debug secara default mati.</div>
            <?php $code("APP_NAME='Aplikasi Saya' php tajdid serve\nAPP_DEBUG=true php tajdid serve 8080"); ?>
        </section>

        <section id="validasi">
            <h2>Validasi input</h2>
            <p>Inject <code>Tajdid\Core\Validation\Validator</code>. Validasi mengembalikan hanya field yang memiliki aturan, tanpa konversi tipe otomatis.</p>
            <?php $code(<<<'CODE'
$data = $validator->validate($request->body(), [
    'email' => 'required|string|email|max:254',
    'name' => 'required|string|min:2|max:100',
    'role' => 'required|in:reader,editor',
    'bio' => 'nullable|string|max:1000',
]);
CODE); ?>
            <p>Aturan tersedia: <code>required</code>, <code>nullable</code>, <code>string</code>, <code>integer</code>, <code>email</code>, <code>min</code>, <code>max</code>, dan <code>in</code>. Batas min/max mengukur panjang string dalam byte, bukan nilai angka.</p>
            <p>Input tidak valid menghasilkan status 422 dengan JSON <code>error</code> dan <code>errors</code> per field. Aturan yang tidak dikenal dianggap error konfigurasi.</p>
        </section>

        <section id="database">
            <h2>Database</h2>
            <p>Inject <code>Tajdid\Core\Database\Connection</code>. Koneksi PDO dibuat saat pertama digunakan dan dipakai bersama dalam satu Application. Default database adalah SQLite di <code>storage/database/app.sqlite</code>.</p>
            <?php $code(<<<'CODE'
$rows = $db->select('SELECT * FROM articles WHERE slug = ?', [$slug]);
$db->execute('INSERT INTO articles (title) VALUES (?)', [$title]);

$db->transaction(function ($db): void {
    $db->execute('UPDATE accounts SET balance = balance - ? WHERE id = ?', [100, 1]);
    $db->execute('UPDATE accounts SET balance = balance + ? WHERE id = ?', [100, 2]);
});

$page = $db->paginate('SELECT id, title FROM articles ORDER BY id', [],
    page: 1, perPage: 20);
CODE); ?>
            <p>Selalu bind nilai input. Nama tabel/kolom dan SQL harus berasal dari kode tepercaya. Pagination maksimal 100 baris dan mengembalikan <code>data</code>, <code>total</code>, <code>page</code>, <code>per_page</code>, dan <code>last_page</code>; SELECT tidak boleh diakhiri semicolon.</p>
            <p>Transaksi bersarang menggunakan savepoint. MySQL/PostgreSQL membutuhkan DSN dan driver PDO yang sesuai; gunakan mesin tabel transaksional dan hindari DDL yang melakukan implicit commit.</p>
        </section>

        <section id="migrasi">
            <h2>Migrasi</h2>
            <p>Buat berkas PHP berurutan seperti <code>database/migrations/001_create_articles.php</code> yang mengembalikan callable.</p>
            <?php $code(<<<'CODE'
<?php
return static function (Tajdid\Core\Database\Connection $db): void {
    $db->execute('CREATE TABLE articles (id INTEGER PRIMARY KEY, title VARCHAR(255) NOT NULL)');
};
CODE); ?>
            <?php $code('php tajdid migrate'); ?>
            <p>Migrasi memakai transaksi per berkas dan checksum untuk mendeteksi perubahan pada migrasi yang sudah diterapkan. Tambahkan berkas baru untuk perubahan schema; jangan mengedit riwayat migrasi.</p>
            <div class="notice">Migrator mendukung SQLite/PostgreSQL, bersifat forward-only, dan belum menyediakan rollback. Jalankan dari satu proses deployment. MySQL tetap didukung untuk query dan transaksi biasa.</div>
        </section>

        <section id="session">
            <h2>Session & CSRF</h2>
            <p>Session dibuka saat dibutuhkan, dengan cookie Secure, HttpOnly, SameSite=Lax, serta strict mode. Timeout default: idle 30 menit dan absolut 8 jam. Konfigurasi ada di <code>config/security.php</code>.</p>
            <?php $code(<<<'CODE'
use Tajdid\Core\Security\Session;
use Tajdid\Core\Http\Middleware\VerifyCsrf;

$routes->get('/csrf-token', fn (Session $session) => [
    'token' => $session->token(),
]);
$routes->post('/profile', App\Actions\UpdateProfile::class)
    ->through(VerifyCsrf::class);
CODE); ?>
            <p>Kirim token sebagai header <code>X-CSRF-Token</code> atau field body <code>_token</code>. Token pada URL ditolak. Lindungi semua rute browser yang mengubah data, termasuk login/logout. Jangan mengubah data melalui GET, HEAD, atau OPTIONS.</p>
            <p>Untuk HTTP localhost, nonaktifkan Secure hanya pada lingkungan pengembangan:</p>
            <?php $code('SESSION_SECURE=false php tajdid serve'); ?>
            <p>Gunakan HTTPS dan cookie Secure pada produksi. Field session berawalan <code>_</code> dicadangkan framework.</p>
        </section>

        <section id="auth">
            <h2>Autentikasi & otorisasi</h2>
            <p>Implementasikan <code>Tajdid\Core\Security\UserProvider</code> pada aplikasi. Method <code>findByCredentials(array $credentials): ?array</code> mengembalikan user dengan <code>id</code> dan hash <code>password</code>. Method <code>findById(string $id): ?array</code> membaca user terbaru. Kembalikan null untuk akun yang tidak boleh mengakses.</p>
            <?php $code(<<<'CODE'
// bootstrap/app.php — buat DatabaseUsers di aplikasi Anda.
$app->container->bind(Tajdid\Core\Security\UserProvider::class,
    fn ($c) => $c->get(App\Security\DatabaseUsers::class));

// Di action login, inject Auth dan Validator.
$data = $validator->validate($request->body(), [
    'email' => 'required|string|email|max:254',
    'password' => 'required|string|max:72',
]);
if (!$auth->attempt(['email' => $data['email']], $data['password'])) {
    throw new Tajdid\Core\Http\HttpException(401, 'Invalid credentials');
}
CODE); ?>
            <p>Inject <code>Security\Auth</code>. Gunakan <code>password_hash()</code> saat menyimpan password; bcrypt dibatasi 72 byte. Pasang RateLimit dan VerifyCsrf pada login, RequireAuth pada rute privat, serta VerifyCsrf pada logout. Login/logout mengganti ID session dan token CSRF; ambil token baru setelah login. <code>$auth->user()</code> menyembunyikan field password, dan <code>$auth->logout()</code> membatalkan session.</p>
            <?php $code(<<<'CODE'
// Daftarkan policy di bootstrap/app.php.
$app->container->get(Tajdid\Core\Security\Gate::class)
    ->define('articles.edit', fn (?array $user, array $article): bool =>
        $user !== null && (string) $article['author_id'] === (string) $user['id']);

// Inject Gate dan Auth ke action, lalu periksa sebelum mengubah data.
$gate->authorize('articles.edit', $auth->user(), $article);
CODE); ?>
            <p>Policy yang tidak terdaftar ditolak. Tabel pengguna, reset password, rehash, MFA, dan token API perlu disiapkan oleh aplikasi.</p>
        </section>

        <section id="keamanan">
            <h2>Keamanan & rate limit</h2>
            <p>Respons dinamis mendapat nosniff, Referrer-Policy, X-Frame-Options=DENY, dan Content Security Policy. CSP default mengizinkan aset dari origin sendiri dan menolak inline script/style. Sesuaikan <code>security.content_security_policy</code> bila dibutuhkan; header aset statis diatur di web server.</p>
            <?php $code(<<<'CODE'
$routes->post('/contact', App\Actions\SendContact::class)
    ->through(Tajdid\Core\Http\Middleware\RateLimit::class);
CODE); ?>
            <p>Default limiter: 60 request per 60 detik per IP, berbagi bucket pada seluruh rute yang memakai middleware tersebut. Penyimpanan file memakai lock di <code>storage/rate-limits/</code> dan ditujukan untuk satu host. Identitas berasal dari REMOTE_ADDR; forwarding header diabaikan. Atur alamat klien di web server hanya dari proxy tepercaya.</p>
            <p>Body default dibatasi 1 MiB: JSON rusak/scalar mendapat 400, body terlalu besar mendapat 413, dan rate limit mendapat 429 dengan Retry-After. Error HTTP diberi Cache-Control: no-store. Error internal disamarkan dan dicatat sebagai JSON melalui logger yang dapat diganti.</p>
        </section>

        <section id="cli">
            <h2>CLI & pengujian</h2>
            <?php $code("php tajdid help\nphp tajdid serve [port]\nphp tajdid routes\nphp tajdid make:action ShowArticle\nphp tajdid migrate\nphp tajdid test"); ?>
            <p><code>php tajdid test</code> dan <code>composer test</code> menjalankan pengujian komponen serta HTTP nyata pada server localhost sementara. Pengujian database dilewati jika pdo_sqlite tidak tersedia. CI menyediakan driver tersebut pada PHP 8.4 dan 8.5.</p>
            <p>Warning dan deprecation menjadi kegagalan pengujian. Session serta bucket rate limit uji dibersihkan setelah pengujian HTTP selesai.</p>
        </section>

        <section id="deployment">
            <h2>Deployment</h2>
            <ul>
                <li>Arahkan document root ke <code>public/</code>; teruskan URL nonstatis ke <code>public/index.php</code>.</li>
                <li>Gunakan HTTPS, <code>APP_DEBUG=false</code>, dan <code>SESSION_SECURE=true</code>.</li>
                <li>Atur batas body pada Nginx/Apache dan post_max_size/upload_max_filesize PHP; form/upload dapat diproses sebelum framework berjalan.</li>
                <li>Batasi izin tulis ke storage dan penyimpanan session di luar public. Bersihkan bucket rate limit lama saat layanan berhenti agar tidak berlomba dengan lock aktif.</li>
                <li>Jalankan migrasi dari satu proses sebelum melayani aplikasi dengan schema baru.</li>
            </ul>
            <p>Server bawaan PHP untuk pengembangan. ORM, queue, cache, CORS, validasi file upload, dan lifecycle worker persisten belum disediakan. Pilih komponen tambahan sesuai kebutuhan aplikasi.</p>
        </section>
        <footer class="docs-footer"><a href="/">← Kembali ke beranda</a><a href="#konten">Kembali ke atas ↑</a></footer>
    </main>
</div>
</body>
</html>

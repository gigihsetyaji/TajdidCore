<?php
declare(strict_types=1);
// This fixture must only run in the isolated integration server.
if (getenv('TAJDID_TEST_HTTP') !== '1') { http_response_code(404); exit; }
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/app.css') { return false; }
$app = require dirname(__DIR__) . '/bootstrap/app.php';
use Tajdid\Core\{Config, Container};
use Tajdid\Core\Http\Request;
use Tajdid\Core\Security\{Auth, Session, UserProvider};
use Tajdid\Core\Http\Middleware\{VerifyCsrf, RequireAuth, RateLimit};
$config = $app->container->get(Config::class);
$app->container->instance(Config::class, new Config([
    'app' => ['debug' => false],
    'security' => array_replace($config->get('security'), [
        'max_body_bytes' => 1024, 'session_secure' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/test/secure-token',
        'rate_directory' => getenv('TAJDID_TEST_DIRECTORY') . '/rate', 'rate_limit' => 2,
    ]),
]));
$app->container->instance(UserProvider::class, new class implements UserProvider {
    public function findByCredentials(array $credentials): ?array
    {
        return ($credentials['email'] ?? '') === 'bayu@example.com'
            ? ['id' => '1', 'password' => password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4])]
            : null;
    }
    public function findById(string $id): ?array { return $id === '1' ? ['id' => '1', 'password' => 'hidden'] : null; }
});
$app->router->get('/test/secure-token', fn (Session $s) => ['token' => $s->token()]);
$app->router->post('/test/expire', function (Session $s): array { $s->put('_last_active', time() - 1801); return ['ok' => true]; })->through(VerifyCsrf::class);
$app->router->get('/test/token', fn (Session $s) => ['token' => $s->token()]);
$app->router->post('/test/login', fn (Request $r, Auth $a) => ['ok' => $a->attempt(['email' => $r->input('email')], (string) $r->input('password'))])->through(VerifyCsrf::class);
$app->router->post('/test/logout', function (Auth $a): array { $a->logout(); return ['ok' => true]; })->through(VerifyCsrf::class);
$app->router->get('/test/private', fn (Auth $a) => ['user' => $a->user()])->through(RequireAuth::class);
$app->router->post('/test/form', fn () => ['ok' => true])->through(VerifyCsrf::class);
$app->router->get('/test/limited', fn () => ['ok' => true])->through(RateLimit::class);
$app->run();

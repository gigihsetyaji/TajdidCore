<?php
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once dirname(__DIR__) . '/bootstrap/autoload.php';
use Tajdid\Core\Container;
use Tajdid\Core\Config;
use Tajdid\Core\Database\Connection;
use Tajdid\Core\Http\Request;
use Tajdid\Core\Http\Response;
use Tajdid\Core\Routing\Router;
use Tajdid\Core\View;
$tests = 0;
function check(bool $condition, string $message): void {
    global $tests;
    if (!$condition) { throw new RuntimeException($message); }
    $tests++;
}
$app = require dirname(__DIR__) . '/bootstrap/app.php';
check($app->handle(new Request('GET', '/'))->status === 200, 'Welcome page');
$response = $app->handle(new Request('GET', '/hello/Bayu%20Putra'));
check(json_decode($response->body, true)['message'] === 'Halo, Bayu Putra!', 'Decoded route parameter');
check($response->headers['X-Content-Type-Options'] === 'nosniff', 'Middleware header');
check($app->handle(new Request('GET', '/missing'))->status === 404, '404 response');
$response = $app->handle(new Request('POST', '/health'));
check($response->status === 405 && $response->headers['Allow'] === 'GET, HEAD', '405 and allowed methods');
check($app->handle(new Request('HEAD', '/health'))->status === 200, 'HEAD fallback');
$response = $app->handle(new Request('POST', '/api/echo', ['name' => 'query'], ['name' => 'body']));
check(json_decode($response->body, true)['data']['name'] === 'body', 'Body precedence and injected request');
$container = new Container();
$container->bind(stdClass::class, fn () => new stdClass(), true);
check($container->get(stdClass::class) === $container->get(stdClass::class), 'Shared binding');
$container->bind('cycle', fn (Container $c) => $c->get('cycle'));
try { $container->get('cycle'); throw new LogicException('Cycle accepted'); }
catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'Circular'), 'Cycle detection'); }
$router = new Router(new Container());
$router->get('/file.txt', fn () => 'literal');
check($router->dispatch(new Request('GET', '/fileXtxt'))->status === 404, 'Regex literals escaped');
$router->get('/pair/{first}/{second}', fn (string $first, string $second) => [$first, $second]);
check($router->dispatch(new Request('GET', '/pair/a/b'))->body === '["a","b"]', 'Multiple route parameters');
check(View::escape('<script>') === '&lt;script&gt;', 'HTML escaping');
try { (new View(dirname(__DIR__) . '/views'))->render('../config/app'); throw new LogicException('Traversal accepted'); }
catch (InvalidArgumentException) { check(true, 'View traversal rejected'); }
$config = new Config(['app' => ['debug' => false]]);
check($config->get('app.debug', true) === false && $config->get('missing', 42) === 42, 'Config defaults');
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $db = new Connection(new Config(['database' => ['dsn' => 'sqlite::memory:']]));
    $db->execute('CREATE TABLE notes (id INTEGER PRIMARY KEY, title TEXT)');
    $db->execute('INSERT INTO notes (title) VALUES (?)', ["Tajdid's first note"]);
    check($db->select('SELECT title FROM notes')[0]['title'] === "Tajdid's first note", 'Bound SQL query');
    try { $db->transaction(function (Connection $db) { $db->execute('DELETE FROM notes'); throw new RuntimeException('rollback'); }); }
    catch (RuntimeException) {}
    check(count($db->select('SELECT * FROM notes')) === 1, 'Transaction rollback');
} else { echo "SKIP: SQLite tests (pdo_sqlite belum tersedia)\n"; }
echo "PASS: $tests checks\n";

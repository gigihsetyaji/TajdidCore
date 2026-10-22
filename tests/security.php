<?php
declare(strict_types=1);

use Tajdid\Core\Http\{HttpException, Request, Response};
use Tajdid\Core\{Config, Container};
use Tajdid\Core\Routing\Router;
use Tajdid\Core\Validation\{Validator, ValidationException};
use Tajdid\Core\Security\Gate;
use Tajdid\Core\Http\Middleware\RateLimit;

$validator = new Validator();
check($validator->validate(['email' => 'bayu@example.com', 'admin' => true], ['email' => 'required|email']) === ['email' => 'bayu@example.com'], 'Validation drops unlisted fields');
try { $validator->validate(['email' => ['bad']], ['email' => 'required|string|email']); throw new LogicException('Array email accepted'); }
catch (ValidationException $e) { check($e->status === 422 && isset($e->errors['email']), 'Validation rejects malformed types'); }
check($validator->validate(['name' => null], ['name' => 'nullable|string']) === ['name' => null], 'Nullable validation');
try { $validator->validate([], ['x' => 'unknown']); throw new LogicException('Invalid rule accepted'); }
catch (InvalidArgumentException) { check(true, 'Unknown validation rule fails explicitly'); }
$gate = new Gate();
check(!$gate->allows('edit', ['id' => '1']), 'Authorization denies undefined ability');
$gate->define('edit', fn (?array $user, string $owner): bool => ($user['id'] ?? null) === $owner);
check($gate->allows('edit', ['id' => '1'], '1') && !$gate->allows('edit', null, '1'), 'Authorization checks ownership');
try { $gate->authorize('edit', ['id' => '2'], '1'); throw new LogicException('Forbidden access allowed'); }
catch (HttpException $e) { check($e->status === 403, 'Authorization returns 403'); }
foreach ([["X-Test\r\nInjected", 'value'], ['X-Test', "value\r\nInjected: yes"], ['X-Test', "\0"]] as [$name, $value]) {
    try { (new Response())->withHeader($name, $value); throw new LogicException('Unsafe header accepted'); }
    catch (InvalidArgumentException) { check(true, 'Header injection rejected'); }
}
$response = (new Response())->withHeader('content-type', 'text/plain');
check(count($response->headers) === 1 && $response->headers['content-type'] === 'text/plain', 'Headers replace case-insensitively');
check((new Request('POST', '/', ['x' => 'query'], ['x' => null]))->input('x', 'fallback') === null, 'Explicit null body does not fall back to query');
$app->router->get('/test-validation', fn () => $validator->validate([], ['name' => 'required']));
$response = $app->handle(new Request('GET', '/test-validation'));
check($response->status === 422 && isset(json_decode($response->body, true)['errors']['name']), 'Application maps validation error');
$app->router->get('/test-forbidden', fn () => throw new HttpException(403, 'Forbidden'));
check($app->handle(new Request('GET', '/test-forbidden'))->status === 403, 'Application maps HTTP exception');
$capturedLogs = new class implements \Tajdid\Core\Log\Logger {
    public array $entries = [];
    public function error(string $message, array $context = []): void { $this->entries[] = [$message, $context]; }
};
$app->container->instance(\Tajdid\Core\Log\Logger::class, $capturedLogs);
$app->router->get('/test-error', fn () => throw new RuntimeException('secret-password'));
$response = $app->handle(new Request('GET', '/test-error'));
check($response->status === 500 && !str_contains($response->body, 'secret-password'), 'Production error hides details');
foreach ([['GET', '/missing'], ['POST', '/health'], ['GET', '/test-error'], ['GET', '/test-forbidden']] as [$method, $path]) {
    $response = $app->handle(new Request($method, $path));
    check(($response->headers['X-Content-Type-Options'] ?? '') === 'nosniff' && isset($response->headers['Content-Security-Policy']), 'Security headers on errors');
}
check(!str_contains(json_encode($capturedLogs->entries), 'secret-password'), 'Error logs omit exception arguments and secrets');
check(!isset($app->handle(new Request('GET', '/missing'))->headers['Allow']), '404 does not emit Allow');
$router = new Router(new Container());
$router->get('/head', fn () => 'GET');
$router->add('HEAD', '/head', fn () => 'HEAD');
check($router->dispatch(new Request('HEAD', '/head'))->body === 'HEAD', 'Explicit HEAD takes priority');
$router->group('/admin', [], function (Router $r): void { $r->get('/users/{id}', fn (string $id) => $id)->name('users.show'); });
check($router->url('users.show', ['id' => 'a/b'], ['q' => 'a b']) === '/admin/users/a%2Fb?q=a%20b', 'Named URL encodes route and query values');
try { $router->url('users.show'); throw new LogicException('Missing URL parameter accepted'); }
catch (InvalidArgumentException) { check(true, 'URL generation requires all parameters'); }
try { $router->get('/head', fn () => 'duplicate'); throw new LogicException('Duplicate route accepted'); }
catch (LogicException $e) { check($e->getMessage() === 'Duplicate route', 'Duplicate route rejected'); }
check((new Container())->call(fn (string ...$values) => $values, ['values' => ['a', 'b']]) === ['a', 'b'], 'Variadic DI supports explicit list');
$directory = sys_get_temp_dir() . '/tajdid-rate-' . bin2hex(random_bytes(8));
$limiter = new RateLimit(new Config(['security' => ['rate_limit' => 1, 'rate_window' => 60, 'rate_directory' => $directory]]));
try {
    $limiter->process(new Request('GET', '/', clientIp: '192.0.2.1'), fn () => new Response());
    try { $limiter->process(new Request('GET', '/', headers: ['X-Forwarded-For' => '192.0.2.2'], clientIp: '192.0.2.1'), fn () => new Response()); throw new LogicException('Rate limit bypassed'); }
    catch (HttpException $e) { check($e->status === 429 && (int) $e->headers['Retry-After'] > 0, 'Rate limit ignores spoofed forwarding header'); }
    check($limiter->process(new Request('GET', '/', clientIp: '192.0.2.2'), fn () => new Response())->status === 200, 'Rate buckets separate client IPs');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    rmdir($directory);
}

final class TestFirstMiddleware implements \Tajdid\Core\Http\Middleware
{
    public function process(Request $request, Closure $next): Response
    {
        return $next($request)->withHeader('X-Global-Test', 'applied');
    }
}
$app->router->through(TestFirstMiddleware::class);
foreach (['/missing', '/test-error', '/test-validation'] as $path) {
    check($app->handle(new Request('GET', $path))->headers['X-Global-Test'] === 'applied', 'Global middleware wraps missing routes and action errors');
}
try {
    $router->group('/temporary', [], function (): void { throw new RuntimeException('registration failed'); });
} catch (RuntimeException) {}
$router->get('/outside', fn () => 'outside');
check($router->dispatch(new Request('GET', '/outside'))->body === 'outside', 'Group context restores after exception');

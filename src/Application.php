<?php
declare(strict_types=1);
namespace Tajdid\Core;
use Tajdid\Core\Http\Request;
use Tajdid\Core\Http\Response;
use Tajdid\Core\Routing\Router;
final class Application
{
    public readonly Container $container;
    public readonly Router $router;
    public function __construct(public readonly string $basePath)
    {
        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
        $this->container->instance(Config::class, Config::fromDirectory($basePath . '/config'));
        $this->container->instance(View::class, new View($basePath . '/views'));
        $this->container->bind(Database\Connection::class, fn (Container $c) => new Database\Connection($c->get(Config::class)), shared: true);
        $this->container->bind(Security\Session::class, fn (Container $c) => new Security\Session($c->get(Config::class)), shared: true);
        $this->container->instance(Log\Logger::class, new Log\ErrorLogLogger());
        $this->container->instance(Security\Gate::class, new Security\Gate());
        $this->router = new Router($this->container);
        $this->container->instance(Router::class, $this->router);
    }
    public function handle(Request $request): Response
    {
        try { $response = $this->router->dispatch($request, $this->error(...)); }
        catch (\Throwable $exception) { $response = $this->error($exception); }
        return $this->secure($response);
    }
    private function error(\Throwable $exception): Response
    {
        if ($exception instanceof Http\HttpException) {
            $data = ['error' => $exception->getMessage()];
            if ($exception instanceof Validation\ValidationException) { $data['errors'] = $exception->errors; }
            return new Response(Response::json($data)->body, $exception->status, array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $exception->headers));
        }
        // Avoid logging request arguments, credentials, or stack traces by default.
        $this->container->get(Log\Logger::class)->error('Unhandled exception', ['exception' => $exception::class, 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
        $debug = $this->container->get(Config::class)->get('app.debug', false);
        return Response::json(['error' => $debug ? $exception->getMessage() : 'Internal server error'], 500);
    }
    private function secure(Response $response): Response
    {
        if ($response->status >= 400) { $response = $response->withHeader('Cache-Control', 'no-store'); }
        return $response->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Content-Security-Policy', $this->container->get(Config::class)->get('security.content_security_policy', "default-src 'self'; script-src 'self'; style-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'"));
    }
    public function run(): void
    {
        try { $request = Request::capture($this->container->get(Config::class)->get('security.max_body_bytes', 1048576)); }
        catch (\Throwable $exception) {
            if ($exception instanceof \JsonException) { $exception = new Http\HttpException(400, 'Invalid JSON body'); }
            $this->secure($this->error($exception))->send(($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD');
            return;
        }
        $this->handle($request)->send($request->method === 'HEAD');
    }
}

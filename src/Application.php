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
        $this->router = new Router($this->container);
        $this->container->instance(Router::class, $this->router);
    }
    public function handle(Request $request): Response
    {
        try { return $this->router->dispatch($request); }
        catch (\Throwable $exception) { return $this->error($exception); }
    }
    private function error(\Throwable $exception): Response
    {
        error_log((string) $exception);
        $debug = $this->container->get(Config::class)->get('app.debug', false);
        return Response::json(['error' => $debug ? $exception->getMessage() : 'Internal server error'], 500);
    }
    public function run(): void
    {
        try { $request = Request::capture(); }
        catch (\JsonException) { Response::json(['error' => 'Invalid JSON body'], 400)->send(); return; }
        $this->handle($request)->send($request->method === 'HEAD');
    }
}

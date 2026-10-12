<?php
declare(strict_types=1);
namespace Tajdid\Core\Routing;
use Tajdid\Core\Container;
use Tajdid\Core\Http\Request;
use Tajdid\Core\Http\Response;
final class Router
{
    private array $routes = [];
    private array $middleware = [];
    public function __construct(private Container $container) {}
    public function add(string $method, string $path, callable|array|string $handler): Route
    {
        if (!str_starts_with($path, '/')) { throw new \InvalidArgumentException('Route paths must start with /'); }
        return $this->routes[] = new Route(strtoupper($method), $path, $handler);
    }
    public function get(string $path, callable|array|string $handler): Route { return $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array|string $handler): Route { return $this->add('POST', $path, $handler); }
    public function through(string ...$middleware): void { $this->middleware = array_merge($this->middleware, $middleware); }
    public function routes(): array { return $this->routes; }
    public function dispatch(Request $request): Response
    {
        $this->container->instance(Request::class, $request);
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route->pattern, $request->path, $matches)) { continue; }
            $allowed[] = $route->method;
            if ($route->method === 'GET') { $allowed[] = 'HEAD'; }
            if ($route->method !== $request->method && !($request->method === 'HEAD' && $route->method === 'GET')) { continue; }
            $parameters = array_combine($route->parameters, array_map('rawurldecode', array_slice($matches, 1)));
            $next = function (Request $request) use ($route, $parameters): Response {
                $this->container->instance(Request::class, $request);
                $result = $this->container->call($route->handler, $parameters);
                return $result instanceof Response ? $result : (is_array($result) ? Response::json($result) : new Response((string) $result));
            };
            foreach (array_reverse(array_merge($this->middleware, $route->middleware)) as $class) {
                $downstream = $next;
                $next = fn (Request $request): Response => $this->container->get($class)->process($request, $downstream);
            }
            return $next($request);
        }
        return Response::json(['error' => $allowed ? 'Method not allowed' : 'Not found'], $allowed ? 405 : 404)
            ->withHeader('Allow', implode(', ', array_unique($allowed)));
    }
}

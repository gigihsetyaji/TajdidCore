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
    private string $prefix = '';
    private array $groupMiddleware = [];
    public function __construct(private Container $container) {}
    public function add(string $method, string $path, callable|array|string $handler): Route
    {
        if (!str_starts_with($path, '/')) { throw new \InvalidArgumentException('Route paths must start with /'); }
        $path = $this->prefix . $path;
        $method = strtoupper($method);
        if (!preg_match('/^[A-Z]+$/D', $method)) { throw new \InvalidArgumentException('Invalid HTTP method'); }
        foreach ($this->routes as $existing) {
            if ($existing->method === $method && $existing->path === $path) { throw new \LogicException('Duplicate route'); }
        }
        $route = new Route($method, $path, $handler);
        $route->through(...$this->groupMiddleware);
        return $this->routes[] = $route;
    }
    public function get(string $path, callable|array|string $handler): Route { return $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array|string $handler): Route { return $this->add('POST', $path, $handler); }
    public function through(string ...$middleware): void { $this->middleware = array_merge($this->middleware, $middleware); }
    public function group(string $prefix, array $middleware, \Closure $register): void
    {
        if ($prefix !== '' && (!str_starts_with($prefix, '/') || str_ends_with($prefix, '/'))) { throw new \InvalidArgumentException('Group prefix must start with / and have no trailing slash'); }
        [$oldPrefix, $oldMiddleware] = [$this->prefix, $this->groupMiddleware];
        $this->prefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        try { $register($this); }
        finally { [$this->prefix, $this->groupMiddleware] = [$oldPrefix, $oldMiddleware]; }
    }
    public function url(string $name, array $parameters = [], array $query = []): string
    {
        $matches = array_filter($this->routes, fn (Route $route) => $route->routeName === $name);
        if (count($matches) !== 1) { throw new \LogicException("Route name must resolve uniquely: $name"); }
        $route = reset($matches);
        $url = $route->path;
        foreach ($route->parameters as $parameter) {
            if (!isset($parameters[$parameter]) || !is_scalar($parameters[$parameter]) || (string) $parameters[$parameter] === '') { throw new \InvalidArgumentException("Missing route parameter: $parameter"); }
            $url = str_replace('{' . $parameter . '}', rawurlencode((string) $parameters[$parameter]), $url);
        }
        return $url . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }
    public function routes(): array { return $this->routes; }
    public function dispatch(Request $request, ?\Closure $onError = null): Response
    {
        $this->container->instance(Request::class, $request);
        $next = function (Request $request) use ($onError): Response {
            try { return $this->match($request); }
            catch (\Throwable $exception) {
                if ($onError === null) { throw $exception; }
                return $onError($exception);
            }
        };
        foreach (array_reverse($this->middleware) as $class) {
            $downstream = $next;
            $next = function (Request $request) use ($class, $downstream): Response {
                $this->container->instance(Request::class, $request);
                return $this->container->get($class)->process($request, $downstream);
            };
        }
        return $next($request);
    }
    private function match(Request $request): Response
    {
        $this->container->instance(Request::class, $request);
        $allowed = [];
        $routes = $this->routes;
        if ($request->method === 'HEAD') {
            $routes = array_merge(array_filter($routes, fn (Route $r) => $r->method === 'HEAD'), array_filter($routes, fn (Route $r) => $r->method !== 'HEAD'));
        }
        foreach ($routes as $route) {
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
            foreach (array_reverse($route->middleware) as $class) {
                $downstream = $next;
                $next = function (Request $request) use ($class, $downstream): Response {
                    $this->container->instance(Request::class, $request);
                    return $this->container->get($class)->process($request, $downstream);
                };
            }
            return $next($request);
        }
        $response = Response::json(['error' => $allowed ? 'Method not allowed' : 'Not found'], $allowed ? 405 : 404);
        return $allowed ? $response->withHeader('Allow', implode(', ', array_unique($allowed))) : $response;
    }
}

<?php
declare(strict_types=1);
namespace Tajdid\Core\Routing;
final class Route
{
    public array $middleware = [];
    public ?string $routeName = null;
    public readonly string $pattern;
    public readonly array $parameters;
    public function __construct(public readonly string $method, public readonly string $path, public readonly mixed $handler)
    {
        $parameters = [];
        $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $match)) {
                if (in_array($match[1], $parameters, true)) { throw new \InvalidArgumentException('Duplicate route parameter'); }
                $parameters[] = $match[1];
                $pattern .= '([^/]+)';
            } else { $pattern .= preg_quote($part, '~'); }
        }
        $this->pattern = '~^' . $pattern . '$~D';
        $this->parameters = $parameters;
    }
    public function name(string $name): self
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $name)) { throw new \InvalidArgumentException('Invalid route name'); }
        $this->routeName = $name;
        return $this;
    }
    public function through(string ...$middleware): self { $this->middleware = array_merge($this->middleware, $middleware); return $this; }
}

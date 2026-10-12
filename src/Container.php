<?php
declare(strict_types=1);
namespace Tajdid\Core;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
final class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $resolving = [];
    public function bind(string $id, Closure $factory, bool $shared = false): void
    {
        $this->bindings[$id] = [$factory, $shared];
        unset($this->instances[$id]);
    }
    public function instance(string $id, object $instance): void { $this->instances[$id] = $instance; }
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) { return $this->instances[$id]; }
        if (isset($this->resolving[$id])) { throw new RuntimeException("Circular dependency: $id"); }
        $this->resolving[$id] = true;
        try {
            if (isset($this->bindings[$id])) {
                [$factory, $shared] = $this->bindings[$id];
                $object = $factory($this);
                if ($shared) { $this->instances[$id] = $object; }
                return $object;
            }
            $class = new ReflectionClass($id);
            if (!$class->isInstantiable()) { throw new RuntimeException("Cannot resolve $id; register a binding."); }
            $constructor = $class->getConstructor();
            return $class->newInstanceArgs($constructor ? $this->arguments($constructor, []) : []);
        } finally { unset($this->resolving[$id]); }
    }
    public function call(callable|array|string $handler, array $parameters = []): mixed
    {
        if (is_string($handler) && class_exists($handler)) { $handler = [$this->get($handler), '__invoke']; }
        if (is_array($handler) && is_string($handler[0])) { $handler[0] = $this->get($handler[0]); }
        $reflection = is_array($handler) ? new ReflectionMethod($handler[0], $handler[1]) : new ReflectionFunction(Closure::fromCallable($handler));
        return $handler(...$this->arguments($reflection, $parameters));
    }
    private function arguments(ReflectionMethod|ReflectionFunction $method, array $parameters): array
    {
        $arguments = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) { $arguments[] = $this->get($type->getName()); }
            elseif (array_key_exists($parameter->getName(), $parameters)) { $arguments[] = $parameters[$parameter->getName()]; }
            elseif ($parameter->isDefaultValueAvailable()) { $arguments[] = $parameter->getDefaultValue(); }
            else { throw new RuntimeException('Cannot resolve parameter $' . $parameter->getName()); }
        }
        return $arguments;
    }
}

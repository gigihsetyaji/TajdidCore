<?php
declare(strict_types=1);
namespace Tajdid\Core;
final class Config
{
    public function __construct(private array $values = []) {}
    public static function fromDirectory(string $directory): self
    {
        $values = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) { $values[basename($file, '.php')] = require $file; }
        return new self($values);
    }
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) { return $default; }
            $value = $value[$part];
        }
        return $value;
    }
}

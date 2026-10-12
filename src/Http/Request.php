<?php
declare(strict_types=1);
namespace Tajdid\Core\Http;
final class Request
{
    private array $headers;
    public function __construct(public readonly string $method, public readonly string $path, private array $query = [], private array $body = [], array $headers = [])
    {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }
    public static function capture(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) { $headers[str_replace('_', '-', substr($key, 5))] = $value; }
        }
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'] ?? '';
        $body = $_POST;
        if (str_contains(strtolower($headers['Content-Type']), 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $body = is_array($decoded) ? $decoded : [];
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', $_GET, $body, $headers);
    }
    public function input(string $key, mixed $default = null): mixed { return $this->body[$key] ?? $this->query[$key] ?? $default; }
    public function header(string $name, ?string $default = null): ?string { return $this->headers[strtolower($name)] ?? $default; }
    public function all(): array { return array_replace($this->query, $this->body); }
}

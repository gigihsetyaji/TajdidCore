<?php
declare(strict_types=1);
namespace Tajdid\Core\Http;
final class Request
{
    private array $headers;
    public function __construct(public readonly string $method, public readonly string $path, private array $query = [], private array $body = [], array $headers = [], public readonly string $clientIp = '127.0.0.1')
    {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }
    public static function capture(int $maxBodyBytes = 1048576): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) { $headers[str_replace('_', '-', substr($key, 5))] = $value; }
        }
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($maxBodyBytes < 1) { throw new \InvalidArgumentException('Body limit must be positive'); }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBodyBytes) { throw new HttpException(413, 'Request body too large'); }
        $body = $_POST;
        if (preg_match('~^application/(?:[a-z0-9!#$&^_.+-]+\+)?json$~i', trim(explode(';', $headers['Content-Type'], 2)[0]))) {
            $raw = file_get_contents('php://input', false, null, 0, $maxBodyBytes + 1);
            if ($raw === false) { throw new HttpException(400, 'Unable to read request body'); }
            if (strlen($raw) > $maxBodyBytes) { throw new HttpException(413, 'Request body too large'); }
            $decoded = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) { throw new HttpException(400, 'JSON body must be an object or array'); }
            $body = $decoded;
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', $_GET, $body, $headers, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
    public function input(string $key, mixed $default = null): mixed { return array_key_exists($key, $this->body) ? $this->body[$key] : ($this->query[$key] ?? $default); }
    public function body(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->body : (array_key_exists($key, $this->body) ? $this->body[$key] : $default); }
    public function query(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->query : (array_key_exists($key, $this->query) ? $this->query[$key] : $default); }
    public function header(string $name, ?string $default = null): ?string { return $this->headers[strtolower($name)] ?? $default; }
    public function all(): array { return array_replace($this->query, $this->body); }
}

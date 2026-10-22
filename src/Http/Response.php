<?php
declare(strict_types=1);
namespace Tajdid\Core\Http;
final class Response
{
    public function __construct(public readonly string $body = '', public readonly int $status = 200, public readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'])
    {
        if ($status < 100 || $status > 599) { throw new \InvalidArgumentException('Invalid response status'); }
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name) || !is_string($value) || preg_match('/[\\x00-\\x1f\\x7f]/', $value)) {
                throw new \InvalidArgumentException('Invalid response header');
            }
        }
    }
    public static function json(mixed $data, int $status = 200): self { return new self(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $status, ['Content-Type' => 'application/json; charset=UTF-8']); }
    public static function redirect(string $url, int $status = 302): self
    {
        if (preg_match('/[\r\n]/', $url)) { throw new \InvalidArgumentException('Invalid redirect URL'); }
        return new self('', $status, ['Location' => $url]);
    }
    public function withHeader(string $name, string $value): self
    {
        $headers = array_filter($this->headers, static fn (string $key): bool => strcasecmp($key, $name) !== 0, ARRAY_FILTER_USE_KEY);
        $headers[$name] = $value;
        return new self($this->body, $this->status, $headers);
    }
    public function send(bool $head = false): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) { header($name . ': ' . $value); }
        if (!$head && $this->status >= 200 && !in_array($this->status, [204, 304], true)) { echo $this->body; }
    }
}

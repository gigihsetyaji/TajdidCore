<?php
declare(strict_types=1);
namespace Tajdid\Core\Http;
final class Response
{
    public function __construct(public readonly string $body = '', public readonly int $status = 200, public readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8']) {}
    public static function json(mixed $data, int $status = 200): self { return new self(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $status, ['Content-Type' => 'application/json; charset=UTF-8']); }
    public static function redirect(string $url, int $status = 302): self
    {
        if (preg_match('/[\r\n]/', $url)) { throw new \InvalidArgumentException('Invalid redirect URL'); }
        return new self('', $status, ['Location' => $url]);
    }
    public function withHeader(string $name, string $value): self { return new self($this->body, $this->status, array_replace($this->headers, [$name => $value])); }
    public function send(bool $head = false): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) { header($name . ': ' . $value); }
        if (!$head) { echo $this->body; }
    }
}

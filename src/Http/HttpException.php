<?php
declare(strict_types=1);
namespace Tajdid\Core\Http;
class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly array $headers = [])
    {
        if ($status < 400 || $status > 599) { throw new \InvalidArgumentException('Invalid HTTP error status'); }
        parent::__construct($message);
    }
}

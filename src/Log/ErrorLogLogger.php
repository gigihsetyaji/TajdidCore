<?php
declare(strict_types=1);
namespace Tajdid\Core\Log;
final class ErrorLogLogger implements Logger
{
    public function error(string $message, array $context = []): void
    {
        error_log(json_encode(['time' => gmdate('c'), 'level' => 'error', 'message' => $message, 'context' => $context], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}

<?php
declare(strict_types=1);
namespace Tajdid\Core\Log;
interface Logger
{
    public function error(string $message, array $context = []): void;
}

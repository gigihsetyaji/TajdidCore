<?php
declare(strict_types=1);
namespace Tajdid\Core;
use Tajdid\Core\Http\Response;
final class View
{
    public function __construct(private string $directory) {}
    public static function escape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public function render(string $name, array $data = [], int $status = 200): Response
    {
        if (!preg_match('/^[a-zA-Z0-9_\/-]+$/D', $name) || str_contains($name, '..')) { throw new \InvalidArgumentException('Invalid view name'); }
        $file = $this->directory . '/' . $name . '.php';
        if (!is_file($file)) { throw new \RuntimeException("View not found: $name"); }
        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data): void { extract($__data, EXTR_SKIP); require $__file; })($file, $data);
            return new Response(ob_get_clean(), $status);
        } catch (\Throwable $exception) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            throw $exception;
        }
    }
}

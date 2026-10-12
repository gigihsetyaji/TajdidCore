<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    foreach (['Tajdid\\Core\\' => __DIR__ . '/../src/', 'App\\' => __DIR__ . '/../app/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require $file; }
            return;
        }
    }
});

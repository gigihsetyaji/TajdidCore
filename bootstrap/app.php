<?php
declare(strict_types=1);
require_once __DIR__ . '/autoload.php';
$app = new Tajdid\Core\Application(dirname(__DIR__));
(require $app->basePath . '/routes/web.php')($app->router);
return $app;

<?php
declare(strict_types=1);
use App\Actions\Welcome;
use App\Actions\Documentation;
use Tajdid\Core\Http\Middleware\RateLimit;
use Tajdid\Core\Http\Request;
use Tajdid\Core\Routing\Router;
return static function (Router $routes): void {
    $routes->get('/', Welcome::class);
    $routes->get('/docs', Documentation::class)->name('docs');
    $routes->get('/health', static fn (): array => ['status' => 'ok', 'framework' => 'TajdidCore']);
    $routes->get('/hello/{name}', static fn (string $name): array => ['message' => "Halo, $name!"]);
    $routes->post('/api/echo', static fn (Request $request): array => ['data' => $request->all()])->through(RateLimit::class);
};

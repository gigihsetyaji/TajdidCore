<?php
declare(strict_types=1);
use App\Actions\Welcome;
use App\Middleware\SecurityHeaders;
use Tajdid\Core\Http\Request;
use Tajdid\Core\Routing\Router;
return static function (Router $routes): void {
    $routes->through(SecurityHeaders::class);
    $routes->get('/', Welcome::class);
    $routes->get('/health', static fn (): array => ['status' => 'ok', 'framework' => 'TajdidCore']);
    $routes->get('/hello/{name}', static fn (string $name): array => ['message' => "Halo, $name!"]);
    $routes->post('/api/echo', static fn (Request $request): array => ['data' => $request->all()]);
};

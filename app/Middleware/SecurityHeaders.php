<?php
declare(strict_types=1);
namespace App\Middleware;
use Tajdid\Core\Http\Middleware;
use Tajdid\Core\Http\Request;
use Tajdid\Core\Http\Response;
final class SecurityHeaders implements Middleware
{
    public function process(Request $request, \Closure $next): Response
    {
        return $next($request)->withHeader('X-Content-Type-Options', 'nosniff')->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}

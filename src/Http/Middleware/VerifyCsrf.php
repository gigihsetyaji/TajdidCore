<?php
declare(strict_types=1);
namespace Tajdid\Core\Http\Middleware;
use Tajdid\Core\Http\{Middleware, Request, Response, HttpException};
use Tajdid\Core\Security\Session;
final class VerifyCsrf implements Middleware
{
    public function __construct(private Session $session) {}
    public function process(Request $request, \Closure $next): Response
    {
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = $request->header('X-CSRF-Token') ?? $request->body('_token');
            if (!is_string($token) || !hash_equals($this->session->token(), $token)) {
                throw new HttpException(403, 'Invalid CSRF token');
            }
        }
        return $next($request)->withHeader('Cache-Control', 'no-store');
    }
}

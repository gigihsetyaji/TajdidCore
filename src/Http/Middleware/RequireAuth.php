<?php
declare(strict_types=1);
namespace Tajdid\Core\Http\Middleware;
use Tajdid\Core\Http\{Middleware, Request, Response, HttpException};
use Tajdid\Core\Security\Auth;
final class RequireAuth implements Middleware
{
    public function __construct(private Auth $auth) {}
    public function process(Request $request, \Closure $next): Response
    {
        if ($this->auth->user() === null) { throw new HttpException(401, 'Authentication required'); }
        return $next($request)->withHeader('Cache-Control', 'no-store');
    }
}

<?php
declare(strict_types=1);
namespace Tajdid\Core\Http;
interface Middleware
{
    public function process(Request $request, \Closure $next): Response;
}

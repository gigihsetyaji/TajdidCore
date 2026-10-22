<?php
declare(strict_types=1);
namespace Tajdid\Core\Http\Middleware;
use Tajdid\Core\Config;
use Tajdid\Core\Http\{Middleware, Request, Response, HttpException};
final class RateLimit implements Middleware
{
    public function __construct(private Config $config) {}
    public function process(Request $request, \Closure $next): Response
    {
        $limit = (int) $this->config->get('security.rate_limit', 60);
        $window = (int) $this->config->get('security.rate_window', 60);
        if ($limit < 1 || $window < 1) { throw new \LogicException('Rate limits must be positive'); }
        $directory = $this->config->get('security.rate_directory');
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { throw new \RuntimeException('Unable to create rate limit directory'); }
        // Deliberately ignore spoofable forwarding headers. One bounded bucket per client IP.
        $file = $directory . '/' . hash('sha256', $request->clientIp) . '.json';
        $handle = fopen($file, 'c+');
        if ($handle === false) { throw new \RuntimeException('Unable to open rate limit bucket'); }
        try {
            if (!flock($handle, LOCK_EX)) { throw new \RuntimeException('Unable to lock rate limit bucket'); }
            $state = json_decode(stream_get_contents($handle, 4096), true);
            $now = time();
            if (!is_array($state) || !is_int($state['expires'] ?? null) || !is_int($state['count'] ?? null) || $state['count'] < 0 || $state['expires'] <= $now) { $state = ['count' => 0, 'expires' => $now + $window]; }
            if ($state['count'] >= $limit) { throw new HttpException(429, 'Too many requests', ['Retry-After' => (string) max(1, $state['expires'] - $now)]); }
            $state['count']++;
            rewind($handle);
            ftruncate($handle, 0);
            if (fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR)) === false) { throw new \RuntimeException('Unable to save rate limit bucket'); }
            fflush($handle);
        } finally { fclose($handle); }
        return $next($request);
    }
}

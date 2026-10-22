<?php
declare(strict_types=1);
namespace Tajdid\Core\Security;
use Tajdid\Core\Config;
final class Session
{
    public function __construct(private Config $config) {}
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        if (!session_start([
            'name' => $this->config->get('security.session_name', 'tajdid_session'),
            'use_strict_mode' => 1, 'use_cookies' => 1, 'use_only_cookies' => 1, 'use_trans_sid' => 0,
            'cookie_httponly' => true, 'cookie_secure' => $this->config->get('security.session_secure', true),
            'cookie_samesite' => 'Lax', 'cookie_path' => '/', 'cookie_domain' => '', 'cookie_lifetime' => 0,
            'cache_limiter' => 'nocache',
        ])) { throw new \RuntimeException('Unable to start session'); }
        $now = time();
        $idle = (int) $this->config->get('security.session_idle_seconds', 1800);
        $absolute = (int) $this->config->get('security.session_absolute_seconds', 28800);
        if ($idle < 1 || $absolute < 1) { throw new \LogicException('Session timeouts must be positive'); }
        if (($now - ($_SESSION['_last_active'] ?? $now)) >= $idle || ($now - ($_SESSION['_created_at'] ?? $now)) >= $absolute) {
            $_SESSION = [];
            if (!session_regenerate_id(true)) { throw new \RuntimeException('Unable to expire session'); }
        }
        $_SESSION['_created_at'] ??= $now;
        $_SESSION['_last_active'] = $now;
    }
    public function get(string $key, mixed $default = null): mixed { $this->start(); return $_SESSION[$key] ?? $default; }
    public function put(string $key, mixed $value): void { $this->start(); $_SESSION[$key] = $value; }
    public function forget(string $key): void { $this->start(); unset($_SESSION[$key]); }
    public function regenerate(): void
    {
        $this->start();
        if (!session_regenerate_id(true)) { throw new \RuntimeException('Unable to regenerate session'); }
    }
    public function invalidate(): void
    {
        $this->start();
        $_SESSION = [];
        $this->regenerate();
    }
    public function token(): string
    {
        $token = $this->get('_csrf');
        if (!is_string($token)) { $token = bin2hex(random_bytes(32)); $this->put('_csrf', $token); }
        return $token;
    }
}

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap/autoload.php';

function assertHttp(bool $ok, string $message): void
{
    global $httpChecks;
    if (!$ok) { throw new RuntimeException($message); }
    $httpChecks++;
}

$httpChecks = 0;
$directory = sys_get_temp_dir() . '/tajdid-http-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) { throw new RuntimeException($error); }
$address = stream_socket_get_name($socket, false);
fclose($socket);
$root = dirname(__DIR__);
$command = [PHP_BINARY, '-d', 'session.save_path=' . $directory, '-d', 'display_errors=0', '-d', 'error_reporting=-1', '-S', $address, '-t', $root . '/public', $root . '/tests/http-router.php'];
$environment = getenv();
$environment['TAJDID_TEST_HTTP'] = '1';
$environment['TAJDID_TEST_DIRECTORY'] = $directory;
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $root, $environment);
if (!is_resource($process)) { throw new RuntimeException('Unable to start HTTP server'); }
fclose($pipes[0]);
try {
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe !== false) { fclose($probe); $ready = true; break; }
        usleep(20000);
    }
    assertHttp($ready, 'HTTP server starts');
    $call = static function (string $method, string $path, string $body = '', array $headers = []) use ($address): array {
        $context = stream_context_create(['http' => ['method' => $method, 'content' => $body,
            'header' => implode("\r\n", array_merge(['Connection: close', 'Content-Type: application/json'], $headers)),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
        $result = file_get_contents('http://' . $address . $path, false, $context);
        $lines = http_get_last_response_headers() ?? [];
        preg_match('/^HTTP\/\S+ (\d+)/', $lines[0] ?? '', $match);
        $responseHeaders = [];
        foreach (array_slice($lines, 1) as $line) {
            if (!str_contains($line, ':')) { continue; }
            [$key, $value] = explode(':', $line, 2);
            $responseHeaders[strtolower($key)] = trim($value);
        }
        return ['status' => (int) ($match[1] ?? 0), 'body' => $result, 'headers' => $responseHeaders];
    };
    $r = $call('GET', '/health');
    assertHttp($r['status'] === 200 && !isset($r['headers']['set-cookie']), 'Stateless health does not start a session');
    $r = $call('GET', '/');
    assertHttp(str_contains($r['body'], '/app.css') && !str_contains($r['body'], '<style>'), 'Welcome supports strict CSP');
    assertHttp($call('GET', '/app.css')['status'] === 200, 'Stylesheet is served');
    $r = $call('POST', '/api/echo', '{broken');
    assertHttp($r['status'] === 400 && isset($r['headers']['content-security-policy']), 'Malformed JSON returns secure 400');
    assertHttp($call('POST', '/api/echo', 'false')['status'] === 400, 'Scalar JSON rejected');
    assertHttp($call('POST', '/api/echo', str_repeat('a', 1025))['status'] === 413, 'Large body rejected');
    assertHttp($call('HEAD', '/health')['body'] === '', 'HEAD suppresses HTTP response body');
    assertHttp(str_contains(strtolower($call('GET', '/test/secure-token')['headers']['set-cookie'] ?? ''), '; secure'), 'Session secure cookie enabled by configuration');
    $r = $call('GET', '/test/private');
    assertHttp($r['status'] === 401, 'Anonymous private request rejected');
    $r = $call('GET', '/test/token', headers: ['Cookie: tajdid_session=attackerchosenid']);
    $cookieHeader = $r['headers']['set-cookie'] ?? '';
    $cookie = explode(';', $cookieHeader)[0];
    assertHttp($cookie !== 'tajdid_session=attackerchosenid' && str_contains(strtolower($cookieHeader), 'httponly') && str_contains(strtolower($cookieHeader), 'samesite=lax'), 'Session rejects unknown ID and sets secure cookie attributes');
    $token = json_decode($r['body'], true)['token'];
    assertHttp(strlen($token) === 64, 'CSRF token generated');
    assertHttp($call('POST', '/test/form', '{}', ['Cookie: ' . $cookie])['status'] === 403, 'CSRF required');
    assertHttp($call('POST', '/test/form', '{"_token":[]}', ['Cookie: ' . $cookie])['status'] === 403, 'Array CSRF token rejected');
    assertHttp($call('POST', '/test/form', '{}', ['Cookie: ' . $cookie, 'X-CSRF-Token: ' . $token])['status'] === 200, 'Valid CSRF accepted');
    assertHttp($call('POST', '/test/form?_token=' . $token, '{}', ['Cookie: ' . $cookie])['status'] === 403, 'CSRF tokens in URL are rejected');
    $r = $call('POST', '/test/login', '{"email":"bayu@example.com","password":"wrong"}', ['Cookie: ' . $cookie, 'X-CSRF-Token: ' . $token]);
    assertHttp(json_decode($r['body'], true)['ok'] === false, 'Wrong password rejected');
    $r = $call('POST', '/test/login', '{"email":"bayu@example.com","password":"test-password"}', ['Cookie: ' . $cookie, 'X-CSRF-Token: ' . $token]);
    $newCookie = explode(';', $r['headers']['set-cookie'] ?? '')[0];
    assertHttp(json_decode($r['body'], true)['ok'] === true && $newCookie !== '' && $newCookie !== $cookie, 'Login verifies password and rotates session ID');
    assertHttp($call('GET', '/test/private', headers: ['Cookie: ' . $cookie])['status'] === 401, 'Pre-login session cannot access private route');
    $r = $call('GET', '/test/private', headers: ['Cookie: ' . $newCookie]);
    assertHttp($r['status'] === 200 && json_decode($r['body'], true)['user'] === ['id' => '1'], 'Authenticated response excludes password hash');
    assertHttp($call('POST', '/test/form', '{}', ['Cookie: ' . $newCookie, 'X-CSRF-Token: ' . $token])['status'] === 403, 'Login rotates CSRF token');
    $r = $call('GET', '/test/token', headers: ['Cookie: ' . $newCookie]);
    $token = json_decode($r['body'], true)['token'];
    $r = $call('POST', '/test/expire', '{}', ['Cookie: ' . $newCookie, 'X-CSRF-Token: ' . $token]);
    assertHttp($r['status'] === 200, 'Session expiry fixture applied');
    assertHttp($call('GET', '/test/private', headers: ['Cookie: ' . $newCookie])['status'] === 401, 'Idle session expires without relying on garbage collection');
    // Establish a fresh authenticated session to verify logout independently.
    $r = $call('GET', '/test/token');
    $cookie = explode(';', $r['headers']['set-cookie'])[0];
    $token = json_decode($r['body'], true)['token'];
    $r = $call('POST', '/test/login', '{"email":"bayu@example.com","password":"test-password"}', ['Cookie: ' . $cookie, 'X-CSRF-Token: ' . $token]);
    $newCookie = explode(';', $r['headers']['set-cookie'])[0];
    $r = $call('GET', '/test/token', headers: ['Cookie: ' . $newCookie]);
    $token = json_decode($r['body'], true)['token'];
    $r = $call('POST', '/test/logout', '{}', ['Cookie: ' . $newCookie, 'X-CSRF-Token: ' . $token]);
    assertHttp($r['status'] === 200, 'Logout succeeds');
    assertHttp($call('GET', '/test/private', headers: ['Cookie: ' . $newCookie])['status'] === 401, 'Logout invalidates authenticated session');
    $call('GET', '/test/limited');
    $call('GET', '/test/limited');
    $r = $call('GET', '/test/limited', headers: ['X-Forwarded-For: 192.0.2.123']);
    assertHttp($r['status'] === 429 && isset($r['headers']['retry-after']), 'HTTP rate limit returns retry interval');
    $log = file_get_contents($directory . '/server.log');
    assertHttp(!preg_match('/PHP (?:Warning|Deprecated|Fatal error)/', $log), 'HTTP server has no PHP warnings or deprecations');
    echo "PASS: $httpChecks HTTP checks\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($directory . '/rate/*') ?: [] as $file) { unlink($file); }
    if (is_dir($directory . '/rate')) { rmdir($directory . '/rate'); }
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    rmdir($directory);
}

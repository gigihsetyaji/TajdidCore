<?php
return [
    'content_security_policy' => "default-src 'self'; script-src 'self'; style-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'",
    'max_body_bytes' => 1048576,
    'session_secure' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'session_idle_seconds' => 1800,
    'session_absolute_seconds' => 28800,
    'session_name' => 'tajdid_session',
    'rate_limit' => 60,
    'rate_window' => 60,
    'rate_directory' => dirname(__DIR__) . '/storage/rate-limits',
];

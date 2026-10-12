<?php
return ['dsn' => getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/storage/database/app.sqlite', 'username' => getenv('DB_USERNAME') ?: null, 'password' => getenv('DB_PASSWORD') ?: null];

<?php
return ['name' => getenv('APP_NAME') ?: 'TajdidCore', 'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN)];

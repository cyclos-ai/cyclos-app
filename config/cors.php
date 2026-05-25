<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'oauth/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('APP_URL', 'http://localhost')],
    'allowed_origins_patterns' => [
        '#^https?://[a-z0-9\-]+\.' . preg_quote(env('TENANT_SUBDOMAIN_SUFFIX', '.clm.test'), '#') . '$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Tenant-ID', 'X-Request-ID'],
    'max_age' => 0,
    'supports_credentials' => true,
];

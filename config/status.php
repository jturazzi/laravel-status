<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitored applications from the environment
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of "Name|https://base-url|token" entries. The token
    | is optional: STATUS_TOKEN (shared token) is used otherwise. These apps
    | are read-only in the UI; apps can also be managed from the /apps page,
    | where they are stored in the database.
    |
    | STATUS_APPS="Billing|https://billing.example.com,CRM|https://crm.example.com|secret"
    |
    */

    'apps' => env('STATUS_APPS', ''),

    'token' => env('STATUS_TOKEN'),

    'path' => env('STATUS_SYSTEM_INFO_PATH', '/api/system-info'),

    // Seconds
    'timeout' => (int) env('STATUS_TIMEOUT', 5),

    'cache_ttl' => (int) env('STATUS_CACHE_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Latest PHP and Laravel releases
    |--------------------------------------------------------------------------
    |
    | Applications are compared with the latest stable releases published on
    | php.net and Packagist (cached, 6 h by default). Disabled or unreachable,
    | the newest version among the monitored applications is used instead.
    |
    */

    'upstream' => [
        'enabled' => (bool) env('STATUS_UPSTREAM_VERSIONS', true),
        'cache_ttl' => (int) env('STATUS_UPSTREAM_CACHE_TTL', 21600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard access (HTTP Basic)
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'username' => env('STATUS_USERNAME'),
        'password' => env('STATUS_PASSWORD'),
    ],

];

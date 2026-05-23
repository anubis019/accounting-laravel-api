<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Define the paths that should be checked for CORS requests.
    |
    */
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
        'user/password',
        'password/reset',
        'password/email',
        'email/verify',
        'verify-email/*',
        'forgot-password',
        'reset-password',
        'mpesa/*',
        'whatsapp/*',
        'webhooks/*',
        '*'
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Define the HTTP methods that are allowed for CORS requests.
    |
    */
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS'
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Define the origins that are allowed to make CORS requests.
    | Use '*' to allow all origins (not recommended for production).
    |
    */
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',
        'http://localhost:8080',
        'http://127.0.0.1',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8000',
        'http://192.168.1.*',
        'https://accounting-frontend-9sc3h1rqc-anubis019s-projects.vercel.app',
        'https://*.railway.app',
        'https://*.vercel.app',
        'https://*.netlify.app',
        'https://*.herokuapp.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Define patterns for allowed origins using regular expressions.
    |
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Define the headers that are allowed in CORS requests.
    | Use '*' to allow all headers.
    |
    */
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'Authorization',
        'Accept',
        'Origin',
        'X-API-Key',
        'X-Auth-Token',
        'X-Socket-Id',
        'X-Inertia',
        'X-XSRF-TOKEN',
        '*'
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Define the headers that should be exposed to the browser.
    |
    */
    'exposed_headers' => [
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-Debug-Token'
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | Define the maximum age (in seconds) for preflight requests to be cached.
    |
    */
    'max_age' => 86400, // 24 hours

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Define whether credentials (cookies, authorization headers) are allowed.
    |
    */
    'supports_credentials' => true,

];
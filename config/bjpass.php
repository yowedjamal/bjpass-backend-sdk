<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BjPass OAuth2/OIDC Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the BjPass authentication package.
    | Publish this file to customize the settings for your application.
    |
    */

    // OIDC Provider Configuration
    'base_url' => env('BJPASS_BASE_URL', 'https://tx-pki.gouv.bj'),
    'auth_server' => env('BJPASS_AUTH_SERVER', 'main-as'),
    'client_id' => env('BJPASS_CLIENT_ID', ''),
    'client_secret' => env('BJPASS_CLIENT_SECRET', ''),
    'redirect_uri' => env('BJPASS_REDIRECT_URI', ''),
    'scope' => env('BJPASS_SCOPE', 'openid profile'),
    'issuer' => env('BJPASS_ISSUER', null),

    // Security Settings
    'jwks_cache_ttl' => env('BJPASS_JWKS_CACHE_TTL', 3600), // 1 hour
    'auth_session_max_age' => env('BJPASS_AUTH_SESSION_MAX_AGE', 600), // 10 minutes
    'max_token_age' => env('BJPASS_MAX_TOKEN_AGE', 300), // 5 minutes
    'revoke_tokens_on_logout' => env('BJPASS_REVOKE_TOKENS_ON_LOGOUT', true),

    // Cookie Settings
    'use_secure_cookies' => env('BJPASS_USE_SECURE_COOKIES', false),
    'session_cookie_name' => env('BJPASS_SESSION_COOKIE_NAME', 'bjpass_session'),
    'session_cookie_lifetime' => env('BJPASS_SESSION_COOKIE_LIFETIME', 60 * 24 * 7), // 7 days

    // HTTP Settings
    'http_timeout' => env('BJPASS_HTTP_TIMEOUT', 30),
    'http_retry_attempts' => env('BJPASS_HTTP_RETRY_ATTEMPTS', 3),
    'http_retry_delay' => env('BJPASS_HTTP_RETRY_DELAY', 1000),

    // Introspection Settings
    'introspection_bearer' => env('BJPASS_INTROSPECTION_BEARER', null),

    // Route Configuration
    'route_prefix' => env('BJPASS_ROUTE_PREFIX', 'auth'),
    'route_middleware' => env('BJPASS_ROUTE_MIDDLEWARE', ['web']),
    'api_middleware' => env('BJPASS_API_MIDDLEWARE', ['api']),

    // Frontend Integration
    'frontend_origin' => env('BJPASS_FRONTEND_ORIGIN', '*'),
    'default_redirect_after_login' => env('BJPASS_DEFAULT_REDIRECT_AFTER_LOGIN', '/'),

    // Middleware Configuration
    'global_middleware' => env('BJPASS_GLOBAL_MIDDLEWARE', false),

    // Logging Configuration
    'log_level' => env('BJPASS_LOG_LEVEL', 'info'),
    'log_authentication_events' => env('BJPASS_LOG_AUTHENTICATION_EVENTS', true),
    'log_token_operations' => env('BJPASS_LOG_TOKEN_OPERATIONS', true),

    // Cache Configuration
    'cache_driver' => env('BJPASS_CACHE_DRIVER', 'default'),
    'cache_prefix' => env('BJPASS_CACHE_PREFIX', 'bjpass'),

    // Session Configuration
    'session_driver' => env('BJPASS_SESSION_DRIVER', 'default'),
    'session_lifetime' => env('BJPASS_SESSION_LIFETIME', 120), // 2 hours

    // Error Handling
    'show_detailed_errors' => env('BJPASS_SHOW_DETAILED_ERRORS', false),
    'custom_error_messages' => [
        'authentication_failed' => 'Authentication failed. Please try again.',
        'invalid_token' => 'Invalid or expired token.',
        'session_expired' => 'Your session has expired. Please login again.',
        'insufficient_permissions' => 'You do not have sufficient permissions.',
    ],

    // Development Settings
    'debug' => env('BJPASS_DEBUG', false),
    'mock_provider' => env('BJPASS_MOCK_PROVIDER', false),
    'test_mode' => env('BJPASS_TEST_MODE', false),
];

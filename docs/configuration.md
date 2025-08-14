# Configuration

## Fichier de configuration

Le package BjPass utilise le fichier `config/bjpass.php` pour sa configuration. Ce fichier est publié automatiquement lors de l'installation.

## Options de configuration

### Configuration OIDC

```php
'base_url' => env('BJPASS_BASE_URL', 'https://your-provider.com'),
'client_id' => env('BJPASS_CLIENT_ID'),
'client_secret' => env('BJPASS_CLIENT_SECRET'),
'redirect_uri' => env('BJPASS_REDIRECT_URI'),
'scope' => env('BJPASS_SCOPE', 'openid profile email'),
```

### Configuration de sécurité

```php
'jwks_cache_ttl' => env('BJPASS_JWKS_CACHE_TTL', 3600),
'session_max_age' => env('BJPASS_SESSION_MAX_AGE', 3600),
'cookie_secure' => env('BJPASS_COOKIE_SECURE', true),
'cookie_samesite' => env('BJPASS_COOKIE_SAMESITE', 'lax'),
'cookie_httponly' => env('BJPASS_COOKIE_HTTPONLY', true),
```

### Configuration des routes

```php
'route_prefix' => env('BJPASS_ROUTE_PREFIX', 'auth'),
'frontend_origin' => env('BJPASS_FRONTEND_ORIGIN'),
'backend_origin' => env('BJPASS_BACKEND_ORIGIN'),
```

### Configuration HTTP

```php
'http_timeout' => env('BJPASS_HTTP_TIMEOUT', 30),
'http_verify_ssl' => env('BJPASS_HTTP_VERIFY_SSL', true),
'http_user_agent' => env('BJPASS_HTTP_USER_AGENT', 'BjPass-Backend-SDK/1.0'),
```

## Variables d'environnement

### Obligatoires

```env
BJPASS_CLIENT_ID=your_client_id
BJPASS_CLIENT_SECRET=your_client_secret
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
BJPASS_PROVIDER_URL=https://your-provider.com
```

### Optionnelles

```env
# Scopes OIDC
BJPASS_SCOPE=openid profile email

# Sécurité
BJPASS_SESSION_MAX_AGE=3600
BJPASS_COOKIE_SECURE=true
BJPASS_COOKIE_SAMESITE=lax
BJPASS_COOKIE_HTTPONLY=true

# Routes
BJPASS_ROUTE_PREFIX=auth
BJPASS_FRONTEND_ORIGIN=https://your-frontend.com

# HTTP
BJPASS_HTTP_TIMEOUT=30
BJPASS_HTTP_VERIFY_SSL=true
```

## Configuration avancée

### Personnalisation des endpoints

```php
'endpoints' => [
    'authorization' => '/oauth2/authorize',
    'token' => '/oauth2/token',
    'userinfo' => '/oauth2/userinfo',
    'introspect' => '/oauth2/introspect',
    'revoke' => '/oauth2/revoke',
    'jwks' => '/.well-known/jwks.json',
],
```

### Configuration du cache JWKS

```php
'jwks' => [
    'cache_ttl' => 3600,
    'cache_key' => 'bjpass_jwks',
    'max_age' => 86400,
],
```

### Configuration des cookies

```php
'cookies' => [
    'secure' => true,
    'samesite' => 'lax',
    'httponly' => true,
    'domain' => null,
    'path' => '/',
],
```

## Validation de la configuration

Le package valide automatiquement la configuration au démarrage. Les erreurs de configuration sont loggées et peuvent être consultées dans les logs Laravel.

## Prochaines étapes

- [Utilisation](usage.md)
- [API Reference](api-reference.md)

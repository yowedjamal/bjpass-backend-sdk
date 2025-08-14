# Troubleshooting

## Problèmes courants et solutions

### 1. Erreurs de configuration

#### Erreur : "Client secret is required"

**Symptôme :**
```
ConfigurationException: Client secret is required
```

**Cause :** Variable d'environnement `BJPASS_CLIENT_SECRET` manquante ou vide.

**Solution :**
```env
# .env
BJPASS_CLIENT_SECRET=your_actual_client_secret
```

**Vérification :**
```bash
php artisan tinker
>>> config('bjpass.client_secret')
```

#### Erreur : "Invalid redirect URI"

**Symptôme :**
```
ConfigurationException: Invalid redirect URI
```

**Cause :** URI de redirection malformée ou non configurée.

**Solution :**
```env
# .env
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
```

**Vérification :**
```bash
php artisan route:list | grep callback
```

### 2. Erreurs d'authentification

#### Erreur : "Invalid state parameter"

**Symptôme :**
```
AuthenticationException: Invalid state parameter
```

**Cause :** Le paramètre `state` n'a pas été correctement transmis ou validé.

**Solutions possibles :**

1. **Vérifier la session :**
```php
// Dans votre contrôleur
dd(session('bjpass_state'));
```

2. **Vérifier la configuration des cookies :**
```env
BJPASS_COOKIE_SAMESITE=lax
BJPASS_COOKIE_HTTPONLY=true
```

3. **Vérifier la configuration de session :**
```php
// config/session.php
'domain' => env('SESSION_DOMAIN', null),
'secure' => env('SESSION_SECURE_COOKIE', true),
```

#### Erreur : "Invalid nonce"

**Symptôme :**
```
InvalidTokenException: Invalid nonce
```

**Cause :** Le nonce généré ne correspond pas à celui dans l'ID token.

**Solution :**
```php
// Vérifier que la session est maintenue
if (!session()->has('bjpass_nonce')) {
    Log::error('Nonce missing from session');
    return redirect('/auth/start');
}
```

### 3. Erreurs de validation de token

#### Erreur : "Token signature verification failed"

**Symptôme :**
```
InvalidTokenException: Token signature verification failed
```

**Cause :** Problème avec les clés JWKS ou la signature du token.

**Solutions :**

1. **Vider le cache JWKS :**
```php
use BjPass\Facades\BjPass;

BjPass::getJwksService()->clearCache();
```

2. **Vérifier l'URL du provider :**
```env
BJPASS_PROVIDER_URL=https://your-provider.com
```

3. **Vérifier l'endpoint JWKS :**
```php
// config/bjpass.php
'endpoints' => [
    'jwks' => '/.well-known/jwks.json',
],
```

#### Erreur : "Token expired"

**Symptôme :**
```
InvalidTokenException: Token expired
```

**Cause :** Le token ID a expiré.

**Solution :**
```php
// Vérifier l'heure du serveur
echo now()->toISOString();

// Vérifier la configuration de timezone
echo config('app.timezone');
```

### 4. Erreurs de communication avec le provider

#### Erreur : "Failed to fetch JWKS"

**Symptôme :**
```
Exception: Failed to fetch JWKS from provider
```

**Cause :** Problème de connectivité ou d'URL.

**Solutions :**

1. **Tester la connectivité :**
```bash
curl -v "https://your-provider.com/.well-known/jwks.json"
```

2. **Vérifier les paramètres HTTP :**
```env
BJPASS_HTTP_TIMEOUT=30
BJPASS_HTTP_VERIFY_SSL=true
```

3. **Vérifier le proxy si applicable :**
```php
// Dans votre AppServiceProvider
Http::withOptions([
    'proxy' => 'http://proxy.company.com:8080'
]);
```

#### Erreur : "Token exchange failed"

**Symptôme :**
```
AuthenticationException: Token exchange failed
```

**Cause :** Échec de l'échange du code d'autorisation.

**Solutions :**

1. **Vérifier les credentials :**
```env
BJPASS_CLIENT_ID=your_client_id
BJPASS_CLIENT_SECRET=your_client_secret
```

2. **Vérifier l'URL de redirection :**
```env
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
```

3. **Vérifier les scopes :**
```env
BJPASS_SCOPE=openid profile email
```

### 5. Problèmes de session et cookies

#### Erreur : "Session not found"

**Symptôme :**
```
Exception: Session not found
```

**Cause :** Problème de configuration de session.

**Solutions :**

1. **Vérifier la configuration de session :**
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'file'),
'lifetime' => env('SESSION_LIFETIME', 120),
'expire_on_close' => false,
```

2. **Vérifier les permissions de stockage :**
```bash
chmod -R 775 storage/framework/sessions
```

3. **Vérifier la configuration du cache :**
```env
CACHE_DRIVER=file
SESSION_DRIVER=file
```

#### Erreur : "Cookie not set"

**Symptôme :** Les cookies d'authentification ne sont pas définis.

**Cause :** Problème de configuration des cookies.

**Solutions :**

1. **Vérifier la configuration des cookies :**
```env
BJPASS_COOKIE_SECURE=true
BJPASS_COOKIE_HTTPONLY=true
BJPASS_COOKIE_SAMESITE=lax
```

2. **Vérifier le domaine :**
```env
BJPASS_COOKIE_DOMAIN=.your-domain.com
```

### 6. Problèmes de performance

#### Lenteur lors de la validation des tokens

**Symptôme :** Validation des tokens très lente.

**Cause :** Cache JWKS non configuré ou expiré.

**Solutions :**

1. **Optimiser le cache JWKS :**
```env
BJPASS_JWKS_CACHE_TTL=3600
```

2. **Utiliser Redis pour le cache :**
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

3. **Vérifier la configuration du cache :**
```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
    ],
],
```

### 7. Problèmes de déploiement

#### Erreur en production : "Class not found"

**Symptôme :**
```
Class 'BjPass\BjPass' not found
```

**Cause :** Autoloader Composer non mis à jour.

**Solution :**
```bash
composer dump-autoload --optimize
```

#### Erreur : "Configuration file not found"

**Symptôme :**
```
Configuration file not found: bjpass
```

**Cause :** Fichier de configuration non publié.

**Solution :**
```bash
php artisan vendor:publish --tag=bjpass-config
```

### 8. Problèmes de sécurité

#### Erreur : "CSRF token mismatch"

**Symptôme :**
```
TokenMismatchException: CSRF token mismatch
```

**Cause :** Protection CSRF activée sur les routes d'authentification.

**Solution :**
```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'auth/*',
    'api/auth/*',
];
```

#### Erreur : "Origin not allowed"

**Symptôme :**
```
Exception: Origin not allowed
```

**Cause :** Configuration CORS trop restrictive.

**Solution :**
```php
// config/cors.php
'allowed_origins' => [
    'https://your-frontend.com',
    'https://your-api.com',
],
```

## Debug et logging

### Activer le mode debug

```env
BJPASS_DEBUG=true
BJPASS_LOG_LEVEL=debug
```

### Vérifier les logs

```bash
tail -f storage/logs/laravel.log | grep BjPass
```

### Utiliser Tinker pour le debug

```bash
php artisan tinker
```

```php
// Vérifier la configuration
config('bjpass');

// Tester la création d'URL
BjPass::createAuthorizationUrl();

// Vérifier le statut d'authentification
BjPass::isAuthenticated();
```

## Tests de diagnostic

### Test de connectivité

```bash
# Test du provider OIDC
curl -v "https://your-provider.com/.well-known/openid_configuration"

# Test des endpoints
curl -v "https://your-provider.com/.well-known/jwks.json"
```

### Test des routes

```bash
# Lister les routes BjPass
php artisan route:list | grep bjpass

# Tester une route spécifique
curl -v "https://your-app.com/auth/status"
```

### Test de session

```php
// Dans Tinker
session(['test' => 'value']);
echo session('test');
```

## Support et ressources

### Documentation officielle

- [Installation](installation.md)
- [Configuration](configuration.md)
- [API Reference](api-reference.md)

### Communauté

- [Issues GitHub](https://github.com/yowedjamal/bjpass-backend-sdk/issues)
- [Discussions GitHub](https://github.com/yowedjamal/bjpass-backend-sdk/discussions)

### Logs et monitoring

```php
// Ajouter des logs personnalisés
Log::info('Custom auth log', [
    'user_id' => $userId,
    'action' => 'login_attempt',
    'timestamp' => now(),
]);
```

## Prochaines étapes

- [Exemples](examples.md)
- [API Reference](api-reference.md)
- [Sécurité](security.md)

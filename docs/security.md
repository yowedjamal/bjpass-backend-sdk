# Sécurité

## Vue d'ensemble

Le BjPass Backend SDK implémente plusieurs couches de sécurité pour protéger vos utilisateurs et votre application contre les attaques courantes.

## Authentification et autorisation

### Validation des tokens JWT

#### Signature numérique

- **Algorithme** : RS256 (RSA + SHA256)
- **Validation** : Via JWKS (JSON Web Key Set) du provider OIDC
- **Cache** : Les clés publiques sont mises en cache pour optimiser les performances

#### Claims OIDC

Le SDK valide automatiquement les claims suivants :

- **`exp`** : Expiration du token
- **`iat`** : Heure d'émission
- **`iss`** : Émetteur (doit correspondre au provider configuré)
- **`aud`** : Audience (doit correspondre au client_id)
- **`nonce`** : Protection contre les attaques par rejeu

### Gestion des sessions

#### Sécurité des cookies

```php
'cookies' => [
    'secure' => true,        // HTTPS uniquement
    'httponly' => true,      // Protection XSS
    'samesite' => 'lax',     // Protection CSRF
    'domain' => null,        // Domaine de l'application
    'path' => '/',           // Chemin des cookies
],
```

#### Rotation des tokens

- **Refresh token** : Rotation automatique à chaque utilisation
- **Session** : Expiration configurable
- **Nettoyage** : Suppression automatique des tokens expirés

## Protection contre les attaques

### CSRF (Cross-Site Request Forgery)

#### Validation du state

```php
// Génération d'un state unique
$state = Str::random(40);
session(['bjpass_state' => $state]);

// Validation lors du callback
if ($request->get('state') !== session('bjpass_state')) {
    throw AuthenticationException::invalidState();
}
```

#### Cookies SameSite

```env
BJPASS_COOKIE_SAMESITE=lax
```

### XSS (Cross-Site Scripting)

#### Cookies HTTPOnly

```env
BJPASS_COOKIE_HTTPONLY=true
```

Les tokens sont stockés dans des cookies HTTPOnly, empêchant l'accès via JavaScript.

### Attaques par rejeu

#### Nonce unique

```php
// Génération d'un nonce unique
$nonce = Str::random(40);
session(['bjpass_nonce' => $nonce]);

// Validation dans l'ID token
if ($claims['nonce'] !== session('bjpass_nonce')) {
    throw InvalidTokenException::invalidNonce();
}
```

#### Expiration des tokens

```php
// Validation automatique de l'expiration
if ($this->isTokenExpired($claims)) {
    throw InvalidTokenException::expired();
}
```

## Configuration de sécurité

### Variables d'environnement critiques

```env
# Ne jamais exposer en production
BJPASS_CLIENT_SECRET=your_super_secret_key

# Configuration de sécurité
BJPASS_COOKIE_SECURE=true
BJPASS_COOKIE_HTTPONLY=true
BJPASS_COOKIE_SAMESITE=lax
BJPASS_SESSION_MAX_AGE=3600
```

### Validation de la configuration

```php
// Vérification automatique au démarrage
if (empty(config('bjpass.client_secret'))) {
    throw new ConfigurationException('Client secret is required');
}

if (!filter_var(config('bjpass.redirect_uri'), FILTER_VALIDATE_URL)) {
    throw new ConfigurationException('Invalid redirect URI');
}
```

## Bonnes pratiques

### En production

1. **HTTPS obligatoire**
   ```env
   BJPASS_COOKIE_SECURE=true
   ```

2. **Domaine strict**
   ```env
   BJPASS_FRONTEND_ORIGIN=https://your-app.com
   BJPASS_BACKEND_ORIGIN=https://your-api.com
   ```

3. **Expiration courte**
   ```env
   BJPASS_SESSION_MAX_AGE=1800  # 30 minutes
   ```

4. **Logs de sécurité**
   ```env
   BJPASS_LOG_LEVEL=info
   BJPASS_DEBUG=false
   ```

### Monitoring et audit

#### Logs de sécurité

```php
// Toutes les tentatives d'authentification sont loggées
Log::info('User authentication attempt', [
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'timestamp' => now(),
]);
```

#### Métriques de sécurité

- Nombre de tentatives d'authentification
- Taux d'échec d'authentification
- Tokens expirés et révoqués
- Tentatives d'accès non autorisées

## Gestion des erreurs sécurisée

### Messages d'erreur

```php
// Ne jamais exposer d'informations sensibles
try {
    $result = BjPass::exchangeCode($code, $state, $sessionId);
} catch (AuthenticationException $e) {
    // Message générique pour l'utilisateur
    return response()->json(['error' => 'Authentication failed'], 400);
    
    // Détails complets dans les logs
    Log::error('Authentication error', $e->getContext());
}
```

### Rate limiting

```php
// Protection contre le brute force
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

## Tests de sécurité

### Validation des tokens

```php
public function test_invalid_token_rejection()
{
    $invalidToken = 'invalid.jwt.token';
    
    $this->expectException(InvalidTokenException::class);
    BjPass::validateIdToken($invalidToken);
}
```

### Protection CSRF

```php
public function test_state_validation()
{
    $invalidState = 'invalid_state';
    
    $this->expectException(AuthenticationException::class);
    BjPass::exchangeCode('code', $invalidState, 'session');
}
```

## Prochaines étapes

- [Intégration Frontend](frontend-integration.md)
- [Exemples](examples.md)
- [Troubleshooting](troubleshooting.md)

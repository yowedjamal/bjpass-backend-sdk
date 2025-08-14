# API Reference

## Classe BjPass

### Méthodes publiques

#### `createAuthorizationUrl(array $params = [])`

Crée une URL d'autorisation OIDC avec PKCE.

**Paramètres :**
- `$params` (array) - Paramètres optionnels
  - `state` (string) - État personnalisé
  - `nonce` (string) - Nonce personnalisé
  - `redirect_after_login` (string) - URL de redirection après connexion

**Retourne :** string - URL d'autorisation

**Exemple :**
```php
$authUrl = BjPass::createAuthorizationUrl([
    'state' => 'custom_state',
    'redirect_after_login' => '/dashboard'
]);
```

#### `exchangeCode(string $code, string $state, string $sessionId)`

Échange le code d'autorisation contre des tokens.

**Paramètres :**
- `$code` (string) - Code d'autorisation
- `$state` (string) - État retourné par le provider
- `$sessionId` (string) - ID de session

**Retourne :** array - Informations utilisateur et tokens

**Exemple :**
```php
try {
    $user = BjPass::exchangeCode($code, $state, session()->getId());
    // $user contient les informations utilisateur
} catch (AuthenticationException $e) {
    // Gérer l'erreur
}
```

#### `validateIdToken(string $idToken)`

Valide un token ID JWT.

**Paramètres :**
- `$idToken` (string) - Token ID à valider

**Retourne :** array - Claims du token

**Exemple :**
```php
try {
    $claims = BjPass::validateIdToken($idToken);
    $userId = $claims['sub'];
} catch (InvalidTokenException $e) {
    // Token invalide
}
```

#### `introspectToken(string $accessToken)`

Interroge le statut d'un token d'accès.

**Paramètres :**
- `$accessToken` (string) - Token d'accès à interroger

**Retourne :** array - Informations sur le token

**Exemple :**
```php
$tokenInfo = BjPass::introspectToken($accessToken);
if ($tokenInfo['active']) {
    // Token valide
}
```

#### `refreshToken(string $refreshToken = null)`

Rafraîchit un token d'accès.

**Paramètres :**
- `$refreshToken` (string|null) - Token de rafraîchissement (optionnel)

**Retourne :** array - Nouveaux tokens

**Exemple :**
```php
try {
    $newTokens = BjPass::refreshToken();
    // Nouveaux tokens stockés automatiquement
} catch (AuthenticationException $e) {
    // Rediriger vers la connexion
}
```

#### `revokeToken(string $token)`

Révoque un token.

**Paramètres :**
- `$token` (string) - Token à révoquer

**Retourne :** bool - Succès de la révocation

**Exemple :**
```php
$revoked = BjPass::revokeToken($accessToken);
if ($revoked) {
    // Token révoqué avec succès
}
```

#### `getUserInfo(string $accessToken = null)`

Récupère les informations utilisateur.

**Paramètres :**
- `$accessToken` (string|null) - Token d'accès (optionnel)

**Retourne :** array|null - Informations utilisateur

**Exemple :**
```php
$userInfo = BjPass::getUserInfo();
if ($userInfo) {
    $email = $userInfo['email'];
    $name = $userInfo['name'];
}
```

#### `isAuthenticated()`

Vérifie si l'utilisateur est authentifié.

**Retourne :** bool - Statut d'authentification

**Exemple :**
```php
if (BjPass::isAuthenticated()) {
    // Utilisateur connecté
} else {
    // Utilisateur non connecté
}
```

#### `logout()`

Déconnecte l'utilisateur.

**Exemple :**
```php
BjPass::logout();
return redirect('/');
```

## Services

### JwksService

#### `getJwks()`

Récupère les clés JWKS depuis le cache ou le provider.

**Retourne :** array - Clés JWKS

#### `clearCache()`

Efface le cache des clés JWKS.

#### `refreshJwks()`

Force le rafraîchissement des clés JWKS.

### TokenService

#### `parseJwt(string $token)`

Parse un token JWT sans validation.

**Paramètres :**
- `$token` (string) - Token JWT

**Retourne :** array - Claims du token

#### `isTokenExpired(array $claims)`

Vérifie si un token est expiré.

**Paramètres :**
- `$claims` (array) - Claims du token

**Retourne :** bool - Token expiré

## Exceptions

### BjPassException

Exception de base pour toutes les erreurs BjPass.

**Méthodes :**
- `getContext()` - Retourne le contexte de l'erreur

### InvalidTokenException

Exception levée lors de la validation de token.

**Méthodes statiques :**
- `expired()` - Token expiré
- `invalidSignature()` - Signature invalide
- `invalidAudience()` - Audience invalide
- `invalidIssuer()` - Émetteur invalide
- `invalidNonce()` - Nonce invalide
- `malformed()` - Token malformé

### AuthenticationException

Exception levée lors du processus d'authentification.

**Méthodes statiques :**
- `invalidState()` - État invalide
- `invalidCode()` - Code invalide
- `codeExchangeFailed()` - Échec de l'échange de code
- `userNotAuthenticated()` - Utilisateur non authentifié

## Middleware

### BjPassAuthMiddleware

Protège les routes en vérifiant l'authentification.

**Utilisation :**
```php
Route::middleware('bjpass.auth')->group(function () {
    // Routes protégées
});
```

## Configuration

### Variables d'environnement

```env
# Configuration OIDC
BJPASS_CLIENT_ID=your_client_id
BJPASS_CLIENT_SECRET=your_client_secret
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
BJPASS_PROVIDER_URL=https://your-provider.com
BJPASS_SCOPE=openid profile email

# Sécurité
BJPASS_SESSION_MAX_AGE=3600
BJPASS_COOKIE_SECURE=true
BJPASS_COOKIE_SAMESITE=lax
BJPASS_COOKIE_HTTPONLY=true

# Routes
BJPASS_ROUTE_PREFIX=auth
BJPASS_FRONTEND_ORIGIN=https://your-frontend.com
```

## Prochaines étapes

- [Sécurité](security.md)
- [Intégration Frontend](frontend-integration.md)
- [Exemples](examples.md)

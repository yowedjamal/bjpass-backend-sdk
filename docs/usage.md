# Utilisation

## Utilisation de base

### Via la façade Laravel

```php
use BjPass\Facades\BjPass;

// Créer l'URL d'autorisation
$authUrl = BjPass::createAuthorizationUrl([
    'state' => 'custom_state',
    'nonce' => 'custom_nonce'
]);

// Vérifier si l'utilisateur est authentifié
if (BjPass::isAuthenticated()) {
    $userInfo = BjPass::getUserInfo();
}

// Valider un token ID
try {
    $claims = BjPass::validateIdToken($idToken);
} catch (InvalidTokenException $e) {
    // Gérer l'erreur
}
```

### Via l'injection de dépendances

```php
use BjPass\BjPass;

class AuthController extends Controller
{
    public function __construct(private BjPass $bjpass) {}

    public function login()
    {
        $authUrl = $this->bjpass->createAuthorizationUrl();
        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        try {
            $user = $this->bjpass->exchangeCode(
                $request->get('code'),
                $request->get('state'),
                session()->getId()
            );
            return response()->json($user);
        } catch (AuthenticationException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

## Flux d'authentification complet

### 1. Démarrer l'authentification

```php
// Dans votre contrôleur
public function startAuth()
{
    $authUrl = BjPass::createAuthorizationUrl([
        'redirect_after_login' => '/dashboard'
    ]);
    
    return redirect($authUrl);
}
```

### 2. Gérer le callback

```php
// Route configurée automatiquement par le package
// GET /auth/callback
// Géré par BjPassAuthController::callback()
```

### 3. Vérifier le statut

```php
// Vérifier si l'utilisateur est connecté
if (BjPass::isAuthenticated()) {
    $user = BjPass::getUserInfo();
    return view('dashboard', compact('user'));
} else {
    return redirect('/login');
}
```

### 4. Déconnexion

```php
public function logout()
{
    BjPass::logout();
    return redirect('/');
}
```

## Gestion des tokens

### Rafraîchir un token

```php
try {
    $newTokens = BjPass::refreshToken();
    // Les nouveaux tokens sont automatiquement stockés
} catch (AuthenticationException $e) {
    // Rediriger vers la connexion
    return redirect('/auth/start');
}
```

### Valider un token

```php
try {
    $claims = BjPass::validateIdToken($idToken);
    $isValid = true;
} catch (InvalidTokenException $e) {
    $isValid = false;
    $error = $e->getMessage();
}
```

### Introspection d'un token

```php
try {
    $tokenInfo = BjPass::introspectToken($accessToken);
    if ($tokenInfo['active']) {
        // Token valide
    }
} catch (AuthenticationException $e) {
    // Token invalide
}
```

## Middleware d'authentification

### Protection des routes

```php
// Dans routes/web.php
Route::middleware('bjpass.auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

### Vérification manuelle

```php
public function protectedMethod()
{
    if (!BjPass::isAuthenticated()) {
        abort(401, 'Non authentifié');
    }
    
    $user = BjPass::getUserInfo();
    // Continuer avec la logique métier
}
```

## Gestion des erreurs

### Capture des exceptions

```php
use BjPass\Exceptions\BjPassException;
use BjPass\Exceptions\InvalidTokenException;
use BjPass\Exceptions\AuthenticationException;

try {
    $result = BjPass::exchangeCode($code, $state, $sessionId);
} catch (InvalidTokenException $e) {
    // Erreur de validation du token
    Log::error('Token invalide', $e->getContext());
    return response()->json(['error' => 'Token invalide'], 400);
} catch (AuthenticationException $e) {
    // Erreur d'authentification
    Log::error('Erreur d\'authentification', $e->getContext());
    return response()->json(['error' => 'Erreur d\'authentification'], 400);
} catch (BjPassException $e) {
    // Erreur générale
    Log::error('Erreur BjPass', $e->getContext());
    return response()->json(['error' => 'Erreur interne'], 500);
}
```

### Logging et debugging

```php
// Activer le mode debug dans la configuration
'debug' => env('BJPASS_DEBUG', false),
'log_level' => env('BJPASS_LOG_LEVEL', 'info'),

// Les erreurs sont automatiquement loggées
// Consultez storage/logs/laravel.log
```

## Exemples avancés

### Configuration personnalisée

```php
// Dans votre ServiceProvider
public function boot()
{
    BjPass::setConfig([
        'session_max_age' => 7200,
        'cookie_samesite' => 'strict',
        'jwks_cache_ttl' => 1800
    ]);
}
```

### Intégration avec d'autres packages

```php
// Avec Laravel Sanctum
if (BjPass::isAuthenticated()) {
    $user = BjPass::getUserInfo();
    $token = $user->createToken('api-token');
    return response()->json(['token' => $token->plainTextToken]);
}
```

## Prochaines étapes

- [API Reference](api-reference.md)
- [Sécurité](security.md)
- [Exemples](examples.md)

# BjPass Backend SDK - Package Laravel

Un package Laravel complet pour l'authentification OAuth2/OIDC avec BjPass, fournissant une intégration clé en main pour votre application backend.

## 🚀 Installation

### 1. Installation via Composer

```bash
composer require yowedjamal/bjpass-backend-sdk
```

### 2. Publication de la configuration

```bash
php artisan vendor:publish --provider="BjPass\Providers\BjPassServiceProvider" --tag=bjpass-config
```

### 3. Configuration des variables d'environnement

Ajoutez ces variables dans votre fichier `.env` :

```env
# Configuration BjPass OIDC
BJPASS_BASE_URL=https://tx-pki.gouv.bj
BJPASS_AUTH_SERVER=main-as
BJPASS_CLIENT_ID=your_client_id
BJPASS_CLIENT_SECRET=your_client_secret
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
BJPASS_SCOPE=openid profile email
BJPASS_ISSUER=https://tx-pki.gouv.bj

# Configuration avancée
BJPASS_FRONTEND_ORIGIN=https://your-frontend.com
BJPASS_USE_SECURE_COOKIES=true
BJPASS_REVOKE_TOKENS_ON_LOGOUT=true
```

### 4. Publication des routes (optionnel)

```bash
php artisan vendor:publish --provider="BjPass\Providers\BjPassServiceProvider" --tag=bjpass-routes
```

## 📋 Configuration

Le package est configuré via le fichier `config/bjpass.php`. Voici les principales options :

### Configuration OIDC
- `base_url` : URL de base du provider OIDC
- `auth_server` : Serveur d'authentification à utiliser
- `client_id` : Identifiant client OAuth2
- `client_secret` : Secret client OAuth2
- `redirect_uri` : URI de redirection après authentification
- `scope` : Scopes OAuth2 demandés

### Configuration de sécurité
- `jwks_cache_ttl` : Durée de vie du cache JWKS (en secondes)
- `auth_session_max_age` : Durée maximale de la session d'authentification
- `max_token_age` : Âge maximal des tokens acceptés
- `revoke_tokens_on_logout` : Révoquer les tokens lors de la déconnexion

### Configuration des cookies
- `use_secure_cookies` : Utiliser des cookies sécurisés
- `session_cookie_name` : Nom du cookie de session
- `session_cookie_lifetime` : Durée de vie du cookie de session

## 🔧 Utilisation

### Utilisation basique avec la façade

```php
use BjPass\Facades\BjPass;

// Créer une URL d'autorisation
$authData = BjPass::createAuthorizationUrl();

// Échanger un code contre des tokens
$result = BjPass::exchangeCode($code, $state);

// Vérifier l'authentification
if (BjPass::isAuthenticated()) {
    $user = BjPass::getUserInfo();
}

// Déconnexion
BjPass::logout();
```

### Utilisation avec injection de dépendances

```php
use BjPass\BjPass;

class AuthController extends Controller
{
    public function __construct(private BjPass $bjpass) {}

    public function login()
    {
        $authData = $this->bjpass->createAuthorizationUrl();
        return redirect()->away($authData['authorization_url']);
    }

    public function callback(Request $request)
    {
        $result = $this->bjpass->exchangeCode(
            $request->query('code'),
            $request->query('state')
        );
        
        return response()->json($result);
    }
}
```

### Middleware d'authentification

Protégez vos routes avec le middleware BjPass :

```php
Route::middleware(['bjpass.auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = request('bjpass_user');
        return view('dashboard', compact('user'));
    });
});
```

## 🌐 Endpoints HTTP intégrés

Le package fournit automatiquement ces endpoints :

### Authentification
- `GET /auth/start` - Démarrer le flux d'authentification
- `GET /auth/callback` - Gérer le retour du provider OIDC
- `GET /auth/error` - Page d'erreur d'authentification

### API
- `GET /auth/api/status` - Vérifier le statut d'authentification
- `GET /auth/api/user` - Obtenir les informations utilisateur
- `POST /auth/api/logout` - Déconnexion
- `POST /auth/api/refresh` - Rafraîchir le token d'accès
- `POST /auth/api/introspect` - Introspecter un token

### Routes protégées
- `GET /auth/protected/dashboard` - Exemple de route protégée

## 🔐 Sécurité

### Gestion des sessions
- Stockage sécurisé des tokens en session Laravel
- Cookies HTTPOnly optionnels pour une sécurité renforcée
- Rotation automatique des refresh tokens

### Validation des tokens
- Validation cryptographique des signatures JWT via JWKS
- Vérification des claims OIDC (aud, iss, exp, iat, nonce)
- Cache intelligent des clés publiques JWKS

### Protection CSRF
- Validation automatique du paramètre `state`
- Vérification de l'origine des requêtes
- Protection contre les attaques de rejeu

## 🔄 Intégration avec le SDK Frontend

### Configuration du frontend

```javascript
const bjpassConfig = {
    // Configuration backend
    backendUrl: 'https://your-backend.com',
    authEndpoints: {
        start: '/auth/start',
        status: '/auth/api/status',
        user: '/auth/api/user',
        logout: '/auth/api/logout'
    },
    
    // Configuration OIDC
    clientId: 'your_client_id',
    scope: 'openid profile email',
    
    // Callbacks
    onSuccess: (user) => {
        console.log('Authentifié:', user);
    },
    onError: (error) => {
        console.error('Erreur:', error);
    }
};
```

### Flux d'authentification

1. **Démarrage** : Le frontend ouvre `/auth/start` dans une popup
2. **Redirection** : L'utilisateur est redirigé vers le provider OIDC
3. **Callback** : Le provider redirige vers `/auth/callback`
4. **Communication** : La page de callback communique avec le frontend via `postMessage`
5. **Vérification** : Le frontend vérifie le statut via `/auth/api/status`

## 🧪 Tests

### Exécution des tests

```bash
composer test
```

### Tests avec couverture

```bash
composer test-coverage
```

### Analyse statique

```bash
composer analyse
```

## 📚 API Reference

### Méthodes principales

#### `createAuthorizationUrl(array $params = [])`
Crée une URL d'autorisation OAuth2 avec PKCE.

**Paramètres :**
- `state` : Paramètre state personnalisé (optionnel)
- `nonce` : Paramètre nonce personnalisé (optionnel)

**Retour :**
```php
[
    'authorization_url' => 'https://...',
    'state' => 'generated_state',
    'nonce' => 'generated_nonce',
    'code_verifier' => 'generated_code_verifier'
]
```

#### `exchangeCode(string $code, string $state)`
Échange un code d'autorisation contre des tokens.

**Retour :**
```php
[
    'user' => [
        'sub' => 'user_id',
        'name' => 'User Name',
        'email' => 'user@example.com'
    ],
    'tokens' => [
        'access_token' => '...',
        'refresh_token' => '...',
        'expires_in' => 3600,
        'token_type' => 'Bearer'
    ]
]
```

#### `validateIdToken(string $idToken, ?string $expectedNonce = null)`
Valide un ID token JWT.

#### `isAuthenticated()`
Vérifie si l'utilisateur est authentifié.

#### `getUserInfo()`
Récupère les informations de l'utilisateur connecté.

#### `logout()`
Déconnecte l'utilisateur et révoque les tokens.

### Services disponibles

#### `getAuthService()`
Retourne l'instance du service d'authentification.

#### `getTokenService()`
Retourne l'instance du service de validation des tokens.

#### `getJwksService()`
Retourne l'instance du service de gestion des JWKS.

## 🚨 Gestion des erreurs

### Exceptions personnalisées

```php
use BjPass\Exceptions\BjPassException;
use BjPass\Exceptions\InvalidTokenException;
use BjPass\Exceptions\AuthenticationException;

try {
    $result = BjPass::exchangeCode($code, $state);
} catch (InvalidTokenException $e) {
    // Token invalide ou expiré
    Log::error('Token invalide: ' . $e->getMessage());
} catch (AuthenticationException $e) {
    // Erreur d'authentification
    Log::error('Erreur auth: ' . $e->getMessage());
} catch (BjPassException $e) {
    // Erreur générale
    Log::error('Erreur BjPass: ' . $e->getMessage());
}
```

### Codes d'erreur

- `invalid_token` : Token invalide ou expiré
- `invalid_state` : Paramètre state invalide
- `authentication_failed` : Échec de l'authentification
- `session_expired` : Session expirée
- `insufficient_permissions` : Permissions insuffisantes

## 🔧 Personnalisation

### Configuration avancée

```php
// Dans config/bjpass.php
'custom_error_messages' => [
    'authentication_failed' => 'Votre authentification a échoué. Veuillez réessayer.',
    'invalid_token' => 'Votre session a expiré. Veuillez vous reconnecter.',
],

'log_level' => 'debug',
'debug' => true,
```

### Middleware personnalisé

```php
// Créer un middleware personnalisé
class CustomBjPassMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!BjPass::isAuthenticated()) {
            // Logique personnalisée
            return redirect('/custom-login');
        }
        
        return $next($request);
    }
}
```

## 📦 Structure du package

```
src/
├── BjPass.php                    # Classe principale
├── Exceptions/                   # Exceptions personnalisées
├── Facades/                      # Facade Laravel
├── Http/                         # Contrôleurs et middleware
│   ├── Controllers/
│   └── Middleware/
├── Providers/                    # ServiceProvider
└── Services/                     # Services métier
    ├── AuthService.php
    ├── JwksService.php
    └── TokenService.php

config/
└── bjpass.php                   # Configuration

routes/
└── bjpass.php                   # Routes intégrées

resources/
└── views/                       # Vues Blade
    ├── success.blade.php
    └── error.blade.php
```

## 🤝 Contribution

1. Fork le projet
2. Créez une branche pour votre fonctionnalité
3. Committez vos changements
4. Poussez vers la branche
5. Ouvrez une Pull Request

## 📄 Licence

Ce package est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🆘 Support

Pour toute question ou problème :

- Ouvrez une issue sur GitHub
- Consultez la documentation
- Contactez l'équipe de développement

---

**Développé avec ❤️ par l'équipe BjPass**

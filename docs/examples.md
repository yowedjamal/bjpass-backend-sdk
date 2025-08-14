# Exemples

## Exemples de base

### 1. Authentification simple

```php
<?php

use BjPass\Facades\BjPass;

class AuthController extends Controller
{
    public function login()
    {
        $authUrl = BjPass::createAuthorizationUrl();
        return redirect($authUrl);
    }

    public function dashboard()
    {
        if (BjPass::isAuthenticated()) {
            $user = BjPass::getUserInfo();
            return view('dashboard', compact('user'));
        }
        
        return redirect('/login');
    }

    public function logout()
    {
        BjPass::logout();
        return redirect('/');
    }
}
```

### 2. Protection de routes avec middleware

```php
// routes/web.php
Route::middleware('bjpass.auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/settings', [SettingsController::class, 'index']);
});

// DashboardController.php
class DashboardController extends Controller
{
    public function index()
    {
        $user = BjPass::getUserInfo();
        return view('dashboard', compact('user'));
    }
}
```

### 3. Gestion des erreurs

```php
use BjPass\Exceptions\BjPassException;
use BjPass\Exceptions\InvalidTokenException;
use BjPass\Exceptions\AuthenticationException;

class AuthController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $user = BjPass::exchangeCode(
                $request->get('code'),
                $request->get('state'),
                session()->getId()
            );
            
            return response()->json([
                'success' => true,
                'user' => $user
            ]);
            
        } catch (InvalidTokenException $e) {
            Log::error('Token validation failed', $e->getContext());
            return response()->json([
                'error' => 'Invalid token',
                'code' => 'TOKEN_INVALID'
            ], 400);
            
        } catch (AuthenticationException $e) {
            Log::error('Authentication failed', $e->getContext());
            return response()->json([
                'error' => 'Authentication failed',
                'code' => 'AUTH_FAILED'
            ], 400);
            
        } catch (BjPassException $e) {
            Log::error('BjPass error', $e->getContext());
            return response()->json([
                'error' => 'Internal error',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }
}
```

## Exemples avancés

### 4. Intégration avec Laravel Sanctum

```php
<?php

use BjPass\Facades\BjPass;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'email', 'name', 'sub', 'provider'
    ];
}

class ApiAuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $user = BjPass::exchangeCode(
                $request->get('code'),
                $request->get('state'),
                session()->getId()
            );
            
            // Créer ou mettre à jour l'utilisateur
            $localUser = User::updateOrCreate(
                ['sub' => $user['sub']],
                [
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'provider' => 'oidc'
                ]
            );
            
            // Créer un token Sanctum
            $token = $localUser->createToken('api-token');
            
            return response()->json([
                'user' => $localUser,
                'token' => $token->plainTextToken
            ]);
            
        } catch (Exception $e) {
            return response()->json(['error' => 'Login failed'], 400);
        }
    }
}
```

### 5. Gestion des permissions

```php
<?php

use BjPass\Facades\BjPass;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        if (!BjPass::isAuthenticated()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $user = BjPass::getUserInfo();
        $userScopes = $user['scope'] ?? '';
        $userScopesArray = explode(' ', $userScopes);
        
        foreach ($scopes as $scope) {
            if (!in_array($scope, $userScopesArray)) {
                return response()->json(['error' => 'Insufficient permissions'], 403);
            }
        }
        
        return $next($request);
    }
}

// Utilisation
Route::middleware(['bjpass.auth', 'permission:admin,write'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
```

### 6. Rafraîchissement automatique des tokens

```php
<?php

use BjPass\Facades\BjPass;

class TokenRefreshMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Vérifier si l'utilisateur est authentifié
            if (BjPass::isAuthenticated()) {
                return $next($request);
            }
            
            // Essayer de rafraîchir le token
            $newTokens = BjPass::refreshToken();
            
            if ($newTokens) {
                return $next($request);
            }
            
        } catch (Exception $e) {
            // Token expiré, rediriger vers la connexion
            return redirect('/auth/start');
        }
        
        return redirect('/auth/start');
    }
}
```

### 7. Intégration avec Vue.js

```javascript
// auth.js
class AuthService {
    constructor() {
        this.user = null;
        this.isAuthenticated = false;
    }

    async checkAuth() {
        try {
            const response = await fetch('/auth/status', {
                credentials: 'include'
            });
            
            if (response.ok) {
                const { authenticated, user } = await response.json();
                this.isAuthenticated = authenticated;
                this.user = user;
                return { authenticated, user };
            }
            
            return { authenticated: false, user: null };
        } catch (error) {
            console.error('Auth check failed:', error);
            return { authenticated: false, user: null };
        }
    }

    async login() {
        window.location.href = '/auth/start';
    }

    async logout() {
        try {
            await fetch('/auth/logout', {
                method: 'POST',
                credentials: 'include'
            });
            
            this.user = null;
            this.isAuthenticated = false;
            window.location.href = '/';
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }
}

// Vue component
const LoginButton = {
    template: `
        <div>
            <button v-if="!isAuthenticated" @click="login">
                Se connecter
            </button>
            <div v-else>
                <span>Bonjour, {{ user.name }}</span>
                <button @click="logout">Se déconnecter</button>
            </div>
        </div>
    `,
    data() {
        return {
            isAuthenticated: false,
            user: null
        };
    },
    async mounted() {
        const { authenticated, user } = await this.authService.checkAuth();
        this.isAuthenticated = authenticated;
        this.user = user;
    },
    methods: {
        async login() {
            await this.authService.login();
        },
        async logout() {
            await this.authService.logout();
        }
    }
};
```

### 8. Tests unitaires

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use BjPass\Facades\BjPass;
use BjPass\Exceptions\InvalidTokenException;
use BjPass\Exceptions\AuthenticationException;

class BjPassTest extends TestCase
{
    public function test_creates_authorization_url()
    {
        $authUrl = BjPass::createAuthorizationUrl();
        
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
        $this->assertStringContainsString('scope=openid', $authUrl);
    }

    public function test_validates_id_token()
    {
        $validToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...';
        
        try {
            $claims = BjPass::validateIdToken($validToken);
            $this->assertIsArray($claims);
        } catch (InvalidTokenException $e) {
            $this->fail('Valid token should not throw exception');
        }
    }

    public function test_rejects_invalid_token()
    {
        $invalidToken = 'invalid.token.here';
        
        $this->expectException(InvalidTokenException::class);
        BjPass::validateIdToken($invalidToken);
    }

    public function test_checks_authentication_status()
    {
        $isAuthenticated = BjPass::isAuthenticated();
        $this->assertIsBool($isAuthenticated);
    }
}
```

### 9. Configuration personnalisée

```php
<?php

// AppServiceProvider.php
use BjPass\Facades\BjPass;

public function boot()
{
    // Configuration personnalisée pour l'environnement
    if (app()->environment('production')) {
        BjPass::setConfig([
            'session_max_age' => 1800,        // 30 minutes
            'cookie_samesite' => 'strict',    // Plus strict en production
            'jwks_cache_ttl' => 3600,         // 1 heure
            'debug' => false,
            'log_level' => 'warning'
        ]);
    }
    
    // Configuration pour le développement
    if (app()->environment('local')) {
        BjPass::setConfig([
            'session_max_age' => 7200,        // 2 heures
            'cookie_samesite' => 'lax',       // Plus permissif en dev
            'debug' => true,
            'log_level' => 'debug'
        ]);
    }
}
```

### 10. Gestion des événements

```php
<?php

use BjPass\Facades\BjPass;
use Illuminate\Support\Facades\Event;

// Créer un listener personnalisé
class AuthEventListener
{
    public function handleUserAuthenticated($event)
    {
        $user = $event->user;
        
        // Log de connexion
        Log::info('User authenticated', [
            'user_id' => $user['sub'],
            'email' => $user['email'],
            'ip' => request()->ip(),
            'timestamp' => now()
        ]);
        
        // Mettre à jour les statistiques
        Cache::increment('auth_count');
        
        // Envoyer une notification
        event(new UserLoggedIn($user));
    }
}

// Enregistrer le listener
Event::listen('bjpass.user.authenticated', AuthEventListener::class);
```

## Prochaines étapes

- [Troubleshooting](troubleshooting.md)
- [API Reference](api-reference.md)
- [Sécurité](security.md)

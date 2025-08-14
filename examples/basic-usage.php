<?php

/**
 * Exemple d'utilisation basique du package BjPass Backend SDK
 * 
 * Ce fichier montre comment utiliser le package dans une application Laravel
 */

use BjPass\Facades\BjPass;

// ============================================================================
// 1. CONFIGURATION DE BASE
// ============================================================================

// La configuration est automatiquement chargée depuis config/bjpass.php
// et les variables d'environnement .env

// ============================================================================
// 2. UTILISATION AVEC LA FACADE
// ============================================================================

// Créer une URL d'autorisation
try {
    $authData = BjPass::createAuthorizationUrl([
        'state' => 'custom_state_123',
        'nonce' => 'custom_nonce_456'
    ]);
    
    echo "URL d'autorisation créée : " . $authData['authorization_url'] . "\n";
    echo "State généré : " . $authData['state'] . "\n";
    echo "Nonce généré : " . $authData['nonce'] . "\n";
    
} catch (Exception $e) {
    echo "Erreur lors de la création de l'URL : " . $e->getMessage() . "\n";
}

// ============================================================================
// 3. ÉCHANGE DE CODE (dans le contrôleur de callback)
// ============================================================================

// Cette méthode serait appelée dans votre contrôleur de callback
function handleCallback($code, $state) {
    try {
        $result = BjPass::exchangeCode($code, $state);
        
        echo "Authentification réussie !\n";
        echo "Utilisateur ID : " . $result['user']['sub'] . "\n";
        echo "Nom : " . ($result['user']['name'] ?? 'N/A') . "\n";
        echo "Email : " . ($result['user']['email'] ?? 'N/A') . "\n";
        
        // Stocker en session ou base de données
        session(['user' => $result['user']]);
        
        return $result;
        
    } catch (Exception $e) {
        echo "Erreur lors de l'échange de code : " . $e->getMessage() . "\n";
        throw $e;
    }
}

// ============================================================================
// 4. VÉRIFICATION DE L'AUTHENTIFICATION
// ============================================================================

// Vérifier si l'utilisateur est connecté
if (BjPass::isAuthenticated()) {
    echo "L'utilisateur est authentifié\n";
    
    // Récupérer les informations utilisateur
    $user = BjPass::getUserInfo();
    echo "Informations utilisateur : " . json_encode($user, JSON_PRETTY_PRINT) . "\n";
    
} else {
    echo "L'utilisateur n'est pas authentifié\n";
}

// ============================================================================
// 5. VALIDATION DE TOKENS
// ============================================================================

// Valider un ID token (si vous en recevez un d'ailleurs part)
function validateToken($idToken) {
    try {
        $payload = BjPass::validateIdToken($idToken);
        echo "Token valide ! Payload : " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        return $payload;
        
    } catch (Exception $e) {
        echo "Token invalide : " . $e->getMessage() . "\n";
        return null;
    }
}

// ============================================================================
// 6. INTROSPECTION DE TOKENS
// ============================================================================

// Introspecter un token d'accès
function introspectToken($accessToken) {
    $result = BjPass::introspectToken($accessToken);
    
    if ($result) {
        echo "Token introspecté : " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        return $result;
    } else {
        echo "Échec de l'introspection du token\n";
        return null;
    }
}

// ============================================================================
// 7. RAFRAÎCHISSEMENT DE TOKENS
// ============================================================================

// Rafraîchir un token d'accès
function refreshToken($refreshToken) {
    try {
        $result = BjPass::refreshToken($refreshToken);
        
        if ($result) {
            echo "Token rafraîchi avec succès\n";
            echo "Nouveau token : " . $result['access_token'] . "\n";
            echo "Expire à : " . date('Y-m-d H:i:s', $result['expires_at']) . "\n";
            return $result;
        }
        
    } catch (Exception $e) {
        echo "Erreur lors du rafraîchissement : " . $e->getMessage() . "\n";
        return null;
    }
}

// ============================================================================
// 8. DÉCONNEXION
// ============================================================================

// Déconnecter l'utilisateur
function logout() {
    try {
        BjPass::logout();
        echo "Utilisateur déconnecté avec succès\n";
        
        // Nettoyer la session
        session()->forget('user');
        
        return true;
        
    } catch (Exception $e) {
        echo "Erreur lors de la déconnexion : " . $e->getMessage() . "\n";
        return false;
    }
}

// ============================================================================
// 9. UTILISATION AVANCÉE
// ============================================================================

// Accéder aux services directement
$authService = BjPass::getAuthService();
$tokenService = BjPass::getJwksService();
$jwksService = BjPass::getJwksService();

// Mettre à jour la configuration
BjPass::updateConfig([
    'scope' => 'openid profile email phone',
    'max_token_age' => 600 // 10 minutes
]);

// Récupérer la configuration actuelle
$currentConfig = BjPass::getConfig();
echo "Configuration actuelle : " . json_encode($currentConfig, JSON_PRETTY_PRINT) . "\n";

// ============================================================================
// 10. GESTION DES ERREURS
// ============================================================================

// Exemple de gestion d'erreurs avec try-catch
function safeOperation($operation) {
    try {
        return $operation();
    } catch (\BjPass\Exceptions\InvalidTokenException $e) {
        echo "Erreur de token : " . $e->getMessage() . "\n";
        // Gérer spécifiquement les erreurs de token
        return null;
    } catch (\BjPass\Exceptions\AuthenticationException $e) {
        echo "Erreur d'authentification : " . $e->getMessage() . "\n";
        // Gérer spécifiquement les erreurs d'authentification
        return null;
    } catch (\BjPass\Exceptions\BjPassException $e) {
        echo "Erreur BjPass : " . $e->getMessage() . "\n";
        // Gérer les erreurs générales
        return null;
    } catch (Exception $e) {
        echo "Erreur inattendue : " . $e->getMessage() . "\n";
        // Gérer les autres erreurs
        return null;
    }
}

// ============================================================================
// 11. EXEMPLE D'UTILISATION DANS UNE ROUTE LARAVEL
// ============================================================================

/*
// Dans routes/web.php ou routes/api.php
Route::get('/auth/start', function () {
    try {
        $authData = BjPass::createAuthorizationUrl();
        return redirect()->away($authData['authorization_url']);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/auth/callback', function (Request $request) {
    try {
        $result = BjPass::exchangeCode(
            $request->query('code'),
            $request->query('state')
        );
        
        // Rediriger vers le frontend avec succès
        return view('auth.success', [
            'user' => $result['user'],
            'frontend_origin' => config('bjpass.frontend_origin')
        ]);
        
    } catch (Exception $e) {
        return view('auth.error', [
            'error' => 'authentication_failed',
            'message' => $e->getMessage()
        ]);
    }
});

Route::middleware(['bjpass.auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = request('bjpass_user');
        return view('dashboard', compact('user'));
    });
    
    Route::get('/profile', function () {
        $user = BjPass::getUserInfo();
        return response()->json($user);
    });
    
    Route::post('/logout', function () {
        BjPass::logout();
        return response()->json(['success' => true]);
    });
});
*/

// ============================================================================
// 12. EXEMPLE D'UTILISATION DANS UN CONTRÔLEUR
// ============================================================================

/*
class AuthController extends Controller
{
    public function start()
    {
        try {
            $authData = BjPass::createAuthorizationUrl();
            return redirect()->away($authData['authorization_url']);
        } catch (Exception $e) {
            Log::error('Failed to start authentication', ['error' => $e->getMessage()]);
            return redirect()->back()->withErrors(['auth' => 'Failed to start authentication']);
        }
    }
    
    public function callback(Request $request)
    {
        try {
            $result = BjPass::exchangeCode(
                $request->query('code'),
                $request->query('state')
            );
            
            // Créer ou mettre à jour l'utilisateur en base
            $user = User::updateOrCreate(
                ['sub' => $result['user']['sub']],
                [
                    'name' => $result['user']['name'] ?? '',
                    'email' => $result['user']['email'] ?? '',
                    'profile_data' => json_encode($result['user'])
                ]
            );
            
            // Connecter l'utilisateur
            Auth::login($user);
            
            return redirect()->intended('/dashboard');
            
        } catch (Exception $e) {
            Log::error('Authentication callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors(['auth' => 'Authentication failed']);
        }
    }
    
    public function status()
    {
        try {
            if (BjPass::isAuthenticated()) {
                $user = BjPass::getUserInfo();
                return response()->json([
                    'authenticated' => true,
                    'user' => $user
                ]);
            }
            
            return response()->json(['authenticated' => false]);
            
        } catch (Exception $e) {
            Log::error('Status check failed', ['error' => $e->getMessage()]);
            return response()->json(['authenticated' => false, 'error' => 'Status check failed']);
        }
    }
}
*/

echo "\n=== Exemple d'utilisation BjPass Backend SDK ===\n";
echo "Ce fichier montre les différentes façons d'utiliser le package.\n";
echo "Décommentez les sections pour les tester.\n";

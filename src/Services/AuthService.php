<?php

namespace BjPass\Services;

use BjPass\Exceptions\AuthenticationException;
use BjPass\Exceptions\InvalidTokenException;
use BjPass\Services\TokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Ramsey\Uuid\Uuid;

class AuthService
{
    protected array $config;
    protected TokenService $tokenService;
    protected JwksService $jwksService;

    public function __construct(array $config, TokenService $tokenService, JwksService $jwksService)
    {
        $this->config = $config;
        $this->tokenService = $tokenService;
        $this->jwksService = $jwksService;
    }

    public function createAuthorizationUrl(array $params = []): array
    {
        // Generate PKCE parameters
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Generate state and nonce
        $state = $params['state'] ?? $this->generateRandomString(32);
        $nonce = $params['nonce'] ?? $this->generateRandomString(32);

        // Store in session for validation
        $sessionData = [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'created_at' => time()
        ];

        Session::put('bjpass_auth_data', $sessionData, [
            'expires' => $this->config['auth_session_max_age'] ?? 600 // 10 minutes
        ]);

        // Build authorization URL
        $baseUrl = rtrim($this->config['base_url'], '/');
        $authUrl = $baseUrl . '/trustedx-authserver/oauth/' . urlencode($this->config['auth_server']);

        $queryParams = [
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $this->config['scope'],
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'login'
        ];

        if (str_contains($this->config['scope'], 'openid')) {
            $queryParams['nonce'] = $nonce;
        }

        $authorizationUrl = $authUrl . '?' . http_build_query($queryParams);

        Log::info('Authorization URL created', [
            'state' => $state,
            'nonce' => $nonce,
            'scope' => $this->config['scope'],
            'session_data' => Session::get('bjpass_auth_data')
        ]);

        return [
            'authorization_url' => $authorizationUrl,
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier
        ];
    }

    public function exchangeCode(string $code, string $state): array
    {
        // Validate state from session
        $sessionData = Session::get('bjpass_auth_data');
        if (!$sessionData || $sessionData['state'] !== $state) {
            throw AuthenticationException::invalidState(
                $sessionData['state'] ?? 'missing',
                $state
            );
        }

        // Check if session is not too old
        $maxAge = $this->config['auth_session_max_age'] ?? 600; // 10 minutes
        if (time() - $sessionData['created_at'] > $maxAge) {
            Session::forget('bjpass_auth_data');
            throw AuthenticationException::codeExchangeFailed('Session expired');
        }

        try {
            // Exchange code for tokens
            $tokenResponse = $this->performTokenExchange($code, $sessionData['code_verifier']);

            // Validate ID token if present
            $userInfo = [];
            if (isset($tokenResponse['id_token'])) {
                $userInfo = $this->tokenService->validateIdToken(
                    $tokenResponse['id_token'],
                    $sessionData['nonce']
                );
            }

            // Store tokens securely
            $this->storeTokens($tokenResponse, $userInfo);

            // Clear auth session data
            Session::forget('bjpass_auth_data');

            Log::info('Code exchanged successfully', [
                'user_id' => $userInfo['sub'] ?? null,
                'has_access_token' => isset($tokenResponse['access_token']),
                'has_refresh_token' => isset($tokenResponse['refresh_token'])
            ]);

            return [
                'user' => $userInfo,
                'tokens' => [
                    'access_token' => $tokenResponse['access_token'] ?? null,
                    'refresh_token' => $tokenResponse['refresh_token'] ?? null,
                    'expires_in' => $tokenResponse['expires_in'] ?? null,
                    'token_type' => $tokenResponse['token_type'] ?? 'Bearer'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Code exchange failed', [
                'error' => $e,
                'state' => $state
            ]);
            throw $e;
        }
    }

    protected function performTokenExchange(string $code, string $codeVerifier): array
    {
        $tokenUrl = $this->buildTokenUrl();

        $response = Http::timeout(30)
            ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
            ->post($tokenUrl . "?grant_type=authorization_code&code=" . $code . "&redirect_uri=" . $this->config['redirect_uri'] . "&code_verifier=" . $codeVerifier);

        if (!$response->successful()) {
            $errorData = $response->json();
            $errorMessage = isset($errorData['error_description']) ? $errorData['error_description'] : (isset($errorData['error']) ? $errorData['error'] : 'Unknown error');
            throw AuthenticationException::codeExchangeFailed($errorMessage);
        }

        $jsonResponse = $response->json();
        return $jsonResponse;
    }

    protected function buildTokenUrl(): string
    {
        $baseUrl = rtrim($this->config['base_url'], '/');
        return $baseUrl . '/trustedx-authserver/oauth/' . urlencode($this->config['auth_server']) . '/token';
    }

    protected function storeTokens(array $tokenResponse, array $userInfo): void
    {
        $sessionData = [
            'user' => $userInfo,
            'access_token' => $tokenResponse['access_token'] ?? null,
            'refresh_token' => $tokenResponse['refresh_token'] ?? null,
            'expires_at' => isset($tokenResponse['expires_in'])
                ? time() + $tokenResponse['expires_in']
                : null,
            'authenticated_at' => time()
        ];

        Session::put('bjpass_user_session', $sessionData);

        // Set secure cookie if configured
        if ($this->config['use_secure_cookies'] ?? false) {
            $cookieName = $this->config['session_cookie_name'] ?? 'bjpass_session';
            $cookieValue = encrypt(json_encode($sessionData));

            cookie()->queue(
                $cookieName,
                $cookieValue,
                $this->config['session_cookie_lifetime'] ?? 60 * 24 * 7, // 7 days
                '/',
                null,
                true, // secure
                false, // httpOnly
                'Lax' // sameSite
            );
        }
    }

    public function refreshAccessToken(): ?array
    {
        $sessionData = Session::get('bjpass_user_session');
        if (!$sessionData || !isset($sessionData['refresh_token'])) {
            return null;
        }

        try {
            $tokenUrl = $this->buildTokenUrl();

            $response = Http::timeout(30)->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $sessionData['refresh_token'],
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret']
            ]);

            if (!$response->successful()) {
                Log::warning('Token refresh failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $tokenData = $response->json();

            // Update session with new tokens
            $sessionData['access_token'] = $tokenData['access_token'] ?? $sessionData['access_token'];
            $sessionData['expires_at'] = isset($tokenData['expires_in'])
                ? time() + $tokenData['expires_in']
                : $sessionData['expires_at'];

            Session::put('bjpass_user_session', $sessionData);

            Log::info('Access token refreshed successfully');

            return [
                'access_token' => $sessionData['access_token'],
                'expires_at' => $sessionData['expires_at']
            ];
        } catch (\Exception $e) {
            Log::error('Token refresh error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getUserInfo(): ?array
    {
        $sessionData = Session::get('bjpass_user_session');
        if (!$sessionData) {
            return null;
        }

        // Check if access token is expired
        if (isset($sessionData['expires_at']) && $sessionData['expires_at'] < time()) {
            // Try to refresh the token
            $refreshed = $this->refreshAccessToken();
            if (!$refreshed) {
                $this->logout();
                return null;
            }
            $sessionData = Session::get('bjpass_user_session');
        }

        return $sessionData['user'] ?? null;
    }

    public function isAuthenticated(): bool
    {
        $sessionData = Session::get('bjpass_user_session');
        if (!$sessionData) {
            return false;
        }

        // Check if access token is expired
        if (isset($sessionData['expires_at']) && $sessionData['expires_at'] < time()) {
            // Try to refresh the token
            $refreshed = $this->refreshAccessToken();
            if (!$refreshed) {
                $this->logout();
                return false;
            }
        }

        return true;
    }

    public function logout(): void
    {
        $sessionData = Session::get('bjpass_user_session');

        // Revoke tokens if configured
        if ($this->config['revoke_tokens_on_logout'] ?? false) {
            if (isset($sessionData['access_token'])) {
                $this->revokeToken($sessionData['access_token'], 'access_token');
            }
            if (isset($sessionData['refresh_token'])) {
                $this->revokeToken($sessionData['refresh_token'], 'refresh_token');
            }
        }

        // Clear session and cookies
        Session::forget('bjpass_user_session');
        Session::forget('bjpass_auth_data');

        if ($this->config['use_secure_cookies'] ?? false) {
            $cookieName = $this->config['session_cookie_name'] ?? 'bjpass_session';
            cookie()->queue(cookie()->forget($cookieName));
        }

        Log::info('User logged out successfully');
    }

    public function revokeToken(string $token, string $tokenTypeHint = 'access_token'): bool
    {
        try {
            $revokeUrl = rtrim($this->config['base_url'], '/') . '/trustedx-authserver/oauth/' .
                urlencode($this->config['auth_server']) . '/revoke';

            $response = Http::timeout(10)->post($revokeUrl, [
                'token' => $token,
                'token_type_hint' => $tokenTypeHint,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret']
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Token revocation failed', [
                'token_type' => $tokenTypeHint,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function introspectToken(string $token): ?array
    {
        try {
            $introspectUrl = rtrim($this->config['base_url'], '/') . '/trustedx-authserver/oauth/' .
                urlencode($this->config['auth_server']) . '/token/verify';

            $response = Http::timeout(10)->post($introspectUrl, [
                'token' => $token
            ], [
                'Authorization' => 'Bearer ' . ($this->config['introspection_bearer'] ?? $this->config['client_secret'])
            ]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::warning('Token introspection failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function generateCodeVerifier(): string
    {
        return $this->generateRandomString(128);
    }

    protected function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    protected function generateRandomString(int $length): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}

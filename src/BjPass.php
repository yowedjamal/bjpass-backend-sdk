<?php

namespace BjPass;

use BjPass\Services\AuthService;
use BjPass\Services\JwksService;
use BjPass\Services\TokenService;
use BjPass\Exceptions\BjPassException;
use Illuminate\Support\Facades\Log;

class BjPass
{
    protected array $config;
    protected AuthService $authService;
    protected TokenService $tokenService;
    protected JwksService $jwksService;

    public function __construct(array $config = [])
    {
        $this->config = $this->mergeConfig($config);
        $this->validateConfig();
        
        $this->jwksService = new JwksService($this->config);
        $this->tokenService = new TokenService($this->config, $this->jwksService);
        $this->authService = new AuthService($this->config, $this->tokenService, $this->jwksService);
    }

    protected function mergeConfig(array $config): array
    {
        $defaults = [
            'base_url' => 'https://tx-pki.gouv.bj',
            'auth_server' => 'main-as',
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',
            'scope' => 'openid profile',
            'issuer' => null,
            'jwks_cache_ttl' => 3600,
            'auth_session_max_age' => 600,
            'max_token_age' => 300,
            'use_secure_cookies' => false,
            'session_cookie_name' => 'bjpass_session',
            'session_cookie_lifetime' => 60 * 24 * 7,
            'revoke_tokens_on_logout' => true,
            'introspection_bearer' => null,
            'http_timeout' => 30,
            'http_retry_attempts' => 3,
            'http_retry_delay' => 1000,
        ];

        return array_merge($defaults, $config);
    }

    protected function validateConfig(): void
    {
        $required = ['client_id', 'client_secret', 'redirect_uri'];
        
        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                throw new BjPassException("Configuration field '{$field}' is required");
            }
        }

        if (!filter_var($this->config['redirect_uri'], FILTER_VALIDATE_URL)) {
            throw new BjPassException("Invalid redirect_uri format");
        }
    }

    /**
     * Create authorization URL for OAuth2/OIDC flow
     */
    public function createAuthorizationUrl(array $params = []): array
    {
        try {
            return $this->authService->createAuthorizationUrl($params);
        } catch (\Exception $e) {
            Log::error('Failed to create authorization URL', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCode(string $code, string $state): array
    {
        try {
            return $this->authService->exchangeCode($code, $state);
        } catch (\Exception $e) {
            Log::error('Failed to exchange code', [
                'error' => $e->getMessage(),
                'code' => substr($code, 0, 10) . '...',
                'state' => $state
            ]);
            throw $e;
        }
    }

    /**
     * Validate ID token signature and claims
     */
    public function validateIdToken(string $idToken, ?string $expectedNonce = null): array
    {
        try {
            return $this->tokenService->validateIdToken($idToken, $expectedNonce);
        } catch (\Exception $e) {
            Log::error('ID token validation failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($idToken, 0, 50) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Introspect access token
     */
    public function introspectToken(string $token): ?array
    {
        try {
            return $this->authService->introspectToken($token);
        } catch (\Exception $e) {
            Log::warning('Token introspection failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken): ?array
    {
        try {
            return $this->authService->refreshAccessToken();
        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Revoke a token
     */
    public function revokeToken(string $token, string $tokenTypeHint = 'access_token'): bool
    {
        try {
            return $this->authService->revokeToken($token, $tokenTypeHint);
        } catch (\Exception $e) {
            Log::warning('Token revocation failed', [
                'error' => $e->getMessage(),
                'token_type' => $tokenTypeHint
            ]);
            return false;
        }
    }

    /**
     * Get user information from current session
     */
    public function getUserInfo(): ?array
    {
        try {
            return $this->authService->getUserInfo();
        } catch (\Exception $e) {
            Log::warning('Failed to get user info', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        try {
            return $this->authService->isAuthenticated();
        } catch (\Exception $e) {
            Log::warning('Authentication check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Logout user and clear session
     */
    public function logout(): void
    {
        try {
            $this->authService->logout();
        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage()
            ]);
            // Continue with logout even if some operations fail
        }
    }

    /**
     * Parse JWT token without validation
     */
    public function parseJwt(string $token): array
    {
        try {
            return $this->tokenService->parseJwt($token);
        } catch (\Exception $e) {
            Log::warning('JWT parsing failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 50) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(string $token): bool
    {
        try {
            return $this->tokenService->isTokenExpired($token);
        } catch (\Exception $e) {
            Log::warning('Token expiration check failed', [
                'error' => $e->getMessage()
            ]);
            return true; // Consider as expired if check fails
        }
    }

    /**
     * Get JWKS from provider
     */
    public function getJwks(): array
    {
        try {
            return $this->jwksService->getJwks();
        } catch (\Exception $e) {
            Log::error('Failed to get JWKS', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Refresh JWKS cache
     */
    public function refreshJwks(): array
    {
        try {
            return $this->jwksService->refreshJwks();
        } catch (\Exception $e) {
            Log::error('Failed to refresh JWKS', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update configuration
     */
    public function updateConfig(array $newConfig): void
    {
        $this->config = array_merge($this->config, $newConfig);
        $this->validateConfig();
        
        // Reinitialize services with new config
        $this->jwksService = new JwksService($this->config);
        $this->tokenService = new TokenService($this->config, $this->jwksService);
        $this->authService = new AuthService($this->config, $this->tokenService, $this->jwksService);
    }

    /**
     * Get service instances for advanced usage
     */
    public function getAuthService(): AuthService
    {
        return $this->authService;
    }

    public function getTokenService(): TokenService
    {
        return $this->tokenService;
    }

    public function getJwksService(): JwksService
    {
        return $this->jwksService;
    }
}

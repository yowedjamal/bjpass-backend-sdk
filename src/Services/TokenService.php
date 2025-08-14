<?php

namespace BjPass\Services;

use BjPass\Exceptions\InvalidTokenException;
use BjPass\Services\JwksService;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Log;

class TokenService
{
    protected array $config;
    protected JwksService $jwksService;

    public function __construct(array $config, JwksService $jwksService)
    {
        $this->config = $config;
        $this->jwksService = $jwksService;
    }

    public function validateIdToken(string $idToken, ?string $expectedNonce = null): array
    {
        try {
            // Parse JWT without verification first to get header
            $tks = explode('.', $idToken);
            if (count($tks) !== 3) {
                throw InvalidTokenException::malformed();
            }

            $header = json_decode(base64_decode(strtr($tks[0], '-_', '+/')), true);
            if (!$header || !isset($header['kid'])) {
                throw InvalidTokenException::malformed();
            }

            // Get the public key for this token
            $publicKey = $this->jwksService->getKeyById($header['kid']);
            if (!$publicKey) {
                throw InvalidTokenException::invalidSignature();
            }

            // Decode and verify the token
            $keys = JWK::parseKeySet(['keys' => [$publicKey]]);
            $decoded = JWT::decode($idToken, $keys, array_keys($keys));

            // Convert to array
            $payload = json_decode(json_encode($decoded), true);

            // Validate claims
            $this->validateClaims($payload, $expectedNonce);

            Log::info('ID token validated successfully', [
                'sub' => $payload['sub'] ?? null,
                'iss' => $payload['iss'] ?? null,
                'aud' => $payload['aud'] ?? null
            ]);

            return $payload;
        } catch (\Exception $e) {
            Log::warning('ID token validation failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($idToken, 0, 50) . '...'
            ]);
            throw $e;
        }
    }

    protected function validateClaims(array $payload, ?string $expectedNonce = null): void
    {
        $now = time();

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw InvalidTokenException::expired();
        }

        // Check issued at (not too old)
        if (isset($payload['iat'])) {
            $maxAge = $this->config['max_token_age'] ?? 300; // 5 minutes default
            if ($now - $payload['iat'] > $maxAge) {
                throw InvalidTokenException::expired();
            }
        }

        // Check issuer
        if (isset($this->config['issuer']) && isset($payload['iss'])) {
            if ($payload['iss'] !== $this->config['issuer']) {
                throw InvalidTokenException::invalidIssuer(
                    $this->config['issuer'],
                    $payload['iss']
                );
            }
        }

        // Check audience
        if (isset($this->config['client_id']) && isset($payload['aud'])) {
            $aud = $payload['aud'];
            if (is_array($aud)) {
                if (!in_array($this->config['client_id'], $aud)) {
                    throw InvalidTokenException::invalidAudience(
                        $this->config['client_id'],
                        implode(', ', $aud)
                    );
                }
            } else {
                if ($aud !== $this->config['client_id']) {
                    throw InvalidTokenException::invalidAudience(
                        $this->config['client_id'],
                        $aud
                    );
                }
            }
        }

        // Check nonce if provided
        if ($expectedNonce && (!isset($payload['nonce']) || $payload['nonce'] !== $expectedNonce)) {
            throw InvalidTokenException::invalidNonce($expectedNonce, $payload['nonce'] ?? 'missing');
        }
    }

    public function parseJwt(string $token): array
    {
        $tks = explode('.', $token);
        if (count($tks) !== 3) {
            throw InvalidTokenException::malformed();
        }

        $header = json_decode(base64_decode(strtr($tks[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($tks[1], '-_', '+/')), true);

        if (!$header || !$payload) {
            throw InvalidTokenException::malformed();
        }

        return [
            'header' => $header,
            'payload' => $payload
        ];
    }

    public function isTokenExpired(string $token): bool
    {
        try {
            $parsed = $this->parseJwt($token);
            $exp = $parsed['payload']['exp'] ?? null;
            
            return $exp && $exp < time();
        } catch (\Exception $e) {
            return true; // Consider malformed tokens as expired
        }
    }
}

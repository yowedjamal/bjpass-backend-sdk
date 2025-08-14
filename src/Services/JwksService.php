<?php

namespace BjPass\Services;

use BjPass\Exceptions\InvalidTokenException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JwksService
{
    protected array $config;
    protected string $cacheKey;
    protected int $ttl;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->cacheKey = 'bjpass_jwks_' . md5($config['base_url']);
        $this->ttl = $config['jwks_cache_ttl'] ?? 3600;
    }

    public function getJwks(): array
    {
        return Cache::remember($this->cacheKey, $this->ttl, function () {
            return $this->fetchJwksFromProvider();
        });
    }

    protected function fetchJwksFromProvider(): array
    {
        $jwksUrl = rtrim($this->config['base_url'], '/') . '/trustedx-authserver/oauth/keys';
        
        try {
            $response = Http::timeout(10)->get($jwksUrl);
            
            if (!$response->successful()) {
                throw new InvalidTokenException("Failed to fetch JWKS from {$jwksUrl}. Status: {$response->status()}");
            }

            $jwks = $response->json();
            
            if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
                throw new InvalidTokenException('Invalid JWKS format received from provider');
            }

            Log::info('JWKS fetched successfully', [
                'url' => $jwksUrl,
                'keys_count' => count($jwks['keys'])
            ]);

            return $jwks;
        } catch (\Exception $e) {
            Log::error('Failed to fetch JWKS', [
                'url' => $jwksUrl,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getKeyById(string $kid): ?array
    {
        $jwks = $this->getJwks();
        
        foreach ($jwks['keys'] as $key) {
            if (isset($key['kid']) && $key['kid'] === $kid) {
                return $key;
            }
        }

        return null;
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }

    public function refreshJwks(): array
    {
        $this->clearCache();
        return $this->getJwks();
    }
}

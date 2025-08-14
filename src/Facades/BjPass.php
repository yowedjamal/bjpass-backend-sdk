<?php

namespace BjPass\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createAuthorizationUrl(array $params = [])
 * @method static array exchangeCode(string $code, string $state)
 * @method static array validateIdToken(string $idToken, ?string $expectedNonce = null)
 * @method static array|null introspectToken(string $token)
 * @method static array|null refreshToken(string $refreshToken)
 * @method static bool revokeToken(string $token, string $tokenTypeHint = 'access_token')
 * @method static array|null getUserInfo()
 * @method static bool isAuthenticated()
 * @method static void logout()
 * @method static array parseJwt(string $token)
 * @method static bool isTokenExpired(string $token)
 * @method static array getJwks()
 * @method static array refreshJwks()
 * @method static array getConfig()
 * @method static void updateConfig(array $newConfig)
 * @method static \BjPass\Services\AuthService getAuthService()
 * @method static \BjPass\Services\TokenService getTokenService()
 * @method static \BjPass\Services\JwksService getJwksService()
 *
 * @see \BjPass\BjPass
 */
class BjPass extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bjpass';
    }
}

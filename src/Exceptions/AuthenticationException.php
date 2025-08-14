<?php

namespace BjPass\Exceptions;

class AuthenticationException extends BjPassException
{
    public static function invalidState(string $expected, string $actual): self
    {
        return new self("Invalid state parameter. Expected: {$expected}, Got: {$actual}");
    }

    public static function invalidCode(): self
    {
        return new self('Invalid authorization code');
    }

    public static function codeExchangeFailed(string $reason): self
    {
        return new self("Code exchange failed: {$reason}");
    }

    public static function userNotAuthenticated(): self
    {
        return new self('User is not authenticated');
    }
}

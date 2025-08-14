<?php

namespace BjPass\Exceptions;

class InvalidTokenException extends BjPassException
{
    public static function expired(): self
    {
        return new self('Token has expired');
    }

    public static function invalidSignature(): self
    {
        return new self('Token signature is invalid');
    }

    public static function invalidAudience(string $expected, string $actual): self
    {
        return new self("Invalid audience. Expected: {$expected}, Got: {$actual}");
    }

    public static function invalidIssuer(string $expected, string $actual): self
    {
        return new self("Invalid issuer. Expected: {$expected}, Got: {$actual}");
    }

    public static function invalidNonce(string $expected, string $actual): self
    {
        return new self("Invalid nonce. Expected: {$expected}, Got: {$actual}");
    }

    public static function malformed(): self
    {
        return new self('Token is malformed');
    }
}

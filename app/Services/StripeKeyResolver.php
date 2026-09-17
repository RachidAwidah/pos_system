<?php

namespace App\Services;

class StripeKeyResolver
{
    public const SECRET_PATTERN = '/\A[sr]k_(test|live)_[A-Za-z0-9]+\z/';

    public const PUBLISHABLE_PATTERN = '/\Apk_(test|live)_[A-Za-z0-9]+\z/';

    public static function secret(mixed $stored, mixed $configured): ?string
    {
        foreach ([$stored, $configured] as $candidate) {
            if (is_string($candidate) && preg_match(self::SECRET_PATTERN, trim($candidate)) === 1
                && ! str_contains(strtoupper($candidate), 'REPLACE')) {
                return trim($candidate);
            }
        }

        return null;
    }
}

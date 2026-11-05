<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use function array_values;
use function count;
use function hash;
use function preg_replace;
use function random_int;
use function strlen;
use function strspn;
use function strtolower;
use function substr;

/**
 * Single use codes for when the user does not have their phone. They look like "7kx2m-p9qrt", which is about 50 bits of
 * randomness. Only a hash is stored.
 */
final class RecoveryCodes
{
    /** No 0, 1, i, l or o, as they are easily mistaken for each other */
    private const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    private const LENGTH = 10;

    /**
     * @return list<string>
     */
    public static function generate(int $count): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $codes[$code] = substr($code, 0, 5) . '-' . substr($code, 5);
        }

        return array_values($codes);
    }

    /**
     * The code as it was generated, ignoring case, spaces and dashes. Null if it cannot be a recovery code.
     */
    public static function normalise(string $code): ?string
    {
        $code = (string) preg_replace('/[\s-]+/', '', strtolower($code));

        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH ? $code : null;
    }

    /**
     * Codes are random enough that a fast hash will do, which lets them be looked up directly.
     */
    public static function hash(string $code): string
    {
        return hash('sha256', self::normalise($code) ?? $code);
    }
}

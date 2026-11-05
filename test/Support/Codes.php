<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support;

use PragmaRX\Google2FA\Google2FA;

/**
 * What the user's app would show.
 */
final class Codes
{
    public static function at(string $secret, int $timestep): string
    {
        return (new Google2FA())->oathTotp($secret, $timestep);
    }
}

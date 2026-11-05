<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Event;

/**
 * An authenticator app was paired, replacing any earlier one. Tell the user, in case it was not them.
 */
final readonly class AuthenticatorAppPaired
{
    public function __construct(
        public object $user,
        public string $userId,
    ) {
    }
}

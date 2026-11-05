<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Event;

/**
 * The user's authenticator app was removed, so they no longer need a second factor for it. Tell the user.
 */
final readonly class AuthenticatorAppRemoved
{
    public function __construct(
        public object $user,
        public string $userId,
    ) {
    }
}

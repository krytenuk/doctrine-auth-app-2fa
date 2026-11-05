<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Event;

/**
 * New recovery codes replaced the old ones. Tell the user, in case it was not them.
 */
final readonly class RecoveryCodesRegenerated
{
    public function __construct(
        public object $user,
        public string $userId,
    ) {
    }
}

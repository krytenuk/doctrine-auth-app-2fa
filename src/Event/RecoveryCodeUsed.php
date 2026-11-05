<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Event;

/**
 * A recovery code was used to log in. Tell the user, and when few are left, suggest making new ones.
 */
final readonly class RecoveryCodeUsed
{
    public function __construct(
        public object $user,
        public string $userId,
        public int    $remaining,
    ) {
    }
}

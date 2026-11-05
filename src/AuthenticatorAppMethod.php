<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\Event\RecoveryCodeUsed;
use FwsDoctrineAuth\TwoFactor\TwoFactorMethodInterface;
use FwsDoctrineAuth\User\UserProviderInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function preg_match;
use function preg_replace;

/**
 * Accepts a code from the user's app, or one of their recovery codes. Each code works once.
 */
final readonly class AuthenticatorAppMethod implements TwoFactorMethodInterface
{
    public function __construct(
        private UserProviderInterface          $users,
        private AuthenticatorAppStoreInterface $store,
        private Totp                           $totp,
        private EventDispatcherInterface       $events,
        private ClockInterface                 $clock,
    ) {
    }

    public function isEnabledFor(object $user): bool
    {
        return $this->store->getSecret($this->users->getId($user)) !== null;
    }

    /**
     * The app makes its own codes, so there is nothing to send.
     */
    public function start(object $user): void
    {
    }

    public function verify(object $user, string $code): bool
    {
        $userId = $this->users->getId($user);
        $secret = $this->store->getSecret($userId);
        if ($secret === null) {
            return false;
        }

        $digits = (string) preg_replace('/\s+/', '', $code);
        if (preg_match('/^\d{6}$/', $digits) === 1) {
            $timestep = $this->totp->verify($secret, $digits, $this->store->getLastTimestep($userId));

            return $timestep !== null && $this->store->useTimestep($userId, $timestep);
        }

        return $this->useRecoveryCode($user, $userId, $code);
    }

    private function useRecoveryCode(object $user, string $userId, string $code): bool
    {
        if (
            RecoveryCodes::normalise($code) === null
            || ! $this->store->useRecoveryCode($userId, RecoveryCodes::hash($code), $this->clock->now())
        ) {
            return false;
        }

        $this->events->dispatch(new RecoveryCodeUsed($user, $userId, $this->store->countRecoveryCodes($userId)));

        return true;
    }
}

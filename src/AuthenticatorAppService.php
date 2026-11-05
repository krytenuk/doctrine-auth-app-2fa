<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\Event\AuthenticatorAppPaired;
use FwsDoctrineAuth\AppTwoFactor\Event\AuthenticatorAppRemoved;
use FwsDoctrineAuth\AppTwoFactor\Event\RecoveryCodesRegenerated;
use FwsDoctrineAuth\Exception\DoctrineAuthException;
use FwsDoctrineAuth\User\UserProviderInterface;
use Mezzio\Session\SessionInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function array_map;
use function is_array;
use function is_string;
use function preg_replace;

/**
 * Pairing and removing a logged-in user's app, and their recovery codes. Logging in with the app is
 * AuthenticatorAppMethod's job, through the core module's TwoFactorLogin.
 *
 * Pairing takes two requests. startPairing() makes a secret and keeps it in the session, and confirmPairing() saves it
 * once the user shows their app produces the right codes. Nothing is saved before then, so a failed scan changes
 * nothing.
 */
final class AuthenticatorAppService
{
    public const SESSION_KEY = 'fws_doctrine_auth.app_2fa.pairing';

    /**
     * @param string $issuer Your site's name, shown in the user's app
     */
    public function __construct(
        private readonly UserProviderInterface $users,
        private readonly AuthenticatorAppStoreInterface $store,
        private readonly Totp $totp,
        private readonly EventDispatcherInterface $events,
        private readonly ClockInterface $clock,
        private readonly string $issuer,
        private readonly int $recoveryCodeCount,
    ) {
    }

    /**
     * Showing the page again gives the same secret, so a user who has already scanned it is not left with a wrong one.
     *
     * @param bool $newSecret Make a new secret anyway, such as when the user asks to start again
     */
    public function startPairing(SessionInterface $session, object $user, bool $newSecret = false): Pairing
    {
        $userId = $this->users->getId($user);
        $secret = $newSecret ? null : $this->pendingSecret($session, $userId);
        if ($secret === null) {
            $secret = $this->totp->generateSecret();
            $session->set(self::SESSION_KEY, ['user_id' => $userId, 'secret' => $secret]);
        }

        return new Pairing($secret, $this->totp->uri($this->issuer, $this->users->getIdentity($user), $secret));
    }

    /**
     * Saves the app if the code is right, replacing any app the user paired before.
     *
     * @return list<string>|null The new recovery codes, to show the user once, or null if the code is wrong or pairing
     *                           was not started. Show the same QR code again, as the user may simply have mistyped
     */
    public function confirmPairing(SessionInterface $session, object $user, string $code): ?array
    {
        $userId = $this->users->getId($user);
        $secret = $this->pendingSecret($session, $userId);
        if ($secret === null) {
            return null;
        }

        $timestep = $this->totp->verify($secret, (string) preg_replace('/\s+/', '', $code));
        if ($timestep === null) {
            return null;
        }

        $this->store->pair($userId, $secret, $timestep, $this->clock->now());
        $session->unset(self::SESSION_KEY);
        $codes = $this->saveRecoveryCodes($userId);
        $this->events->dispatch(new AuthenticatorAppPaired($user, $userId));

        return $codes;
    }

    public function isPaired(object $user): bool
    {
        return $this->store->getSecret($this->users->getId($user)) !== null;
    }

    /**
     * Ask for the user's password or a current code before calling this, so that someone using a session left logged
     * in cannot turn two factor authentication off.
     */
    public function unpair(object $user): void
    {
        $userId = $this->users->getId($user);
        if ($this->store->getSecret($userId) === null) {
            return;
        }

        $this->store->unpair($userId);
        $this->events->dispatch(new AuthenticatorAppRemoved($user, $userId));
    }

    /**
     * Replaces the user's recovery codes, so the old ones stop working.
     *
     * @return list<string> To show the user once
     */
    public function regenerateRecoveryCodes(object $user): array
    {
        $userId = $this->users->getId($user);
        if ($this->store->getSecret($userId) === null) {
            throw new DoctrineAuthException('Recovery codes need an authenticator app to be paired first');
        }

        $codes = $this->saveRecoveryCodes($userId);
        $this->events->dispatch(new RecoveryCodesRegenerated($user, $userId));

        return $codes;
    }

    public function countRecoveryCodes(object $user): int
    {
        return $this->store->countRecoveryCodes($this->users->getId($user));
    }

    /**
     * @return list<string>
     */
    private function saveRecoveryCodes(string $userId): array
    {
        $codes = RecoveryCodes::generate($this->recoveryCodeCount);
        $this->store->replaceRecoveryCodes(
            $userId,
            array_map(RecoveryCodes::hash(...), $codes),
            $this->clock->now()
        );

        return $codes;
    }

    /**
     * The secret is tied to the user, so that it cannot be confirmed by someone else who logs in on the same browser.
     */
    private function pendingSecret(SessionInterface $session, string $userId): ?string
    {
        $pending = $session->get(self::SESSION_KEY);

        return is_array($pending) && ($pending['user_id'] ?? null) === $userId && is_string($pending['secret'] ?? null)
            ? $pending['secret']
            : null;
    }
}

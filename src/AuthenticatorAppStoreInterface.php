<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use DateTimeImmutable;

/**
 * Where paired apps and recovery codes are kept. The default stores them with Doctrine.
 */
interface AuthenticatorAppStoreInterface
{
    public function getSecret(string $userId): ?string;

    public function getLastTimestep(string $userId): ?int;

    /**
     * Replaces any app the user paired before, and removes its recovery codes.
     *
     * @param int $timestep The step of the code used to confirm pairing, so it cannot be used again to log in
     */
    public function pair(string $userId, string $secret, int $timestep, DateTimeImmutable $now): void;

    /**
     * Marks a time step as used, so its code cannot be used again.
     *
     * @return bool False if this step or a later one already was. This has to be atomic, so that two requests cannot
     *              both use the same code
     */
    public function useTimestep(string $userId, int $timestep): bool;

    /**
     * Removes the app and its recovery codes.
     */
    public function unpair(string $userId): void;

    /**
     * @param list<string> $codeHashes
     */
    public function replaceRecoveryCodes(string $userId, array $codeHashes, DateTimeImmutable $now): void;

    /**
     * @return bool False if there is no such unused code. This has to be atomic, as useTimestep() does
     */
    public function useRecoveryCode(string $userId, string $codeHash, DateTimeImmutable $now): bool;

    /**
     * How many unused recovery codes the user has.
     */
    public function countRecoveryCodes(string $userId): int;
}

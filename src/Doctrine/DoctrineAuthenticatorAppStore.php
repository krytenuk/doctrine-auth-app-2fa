<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppStoreInterface;
use FwsDoctrineAuth\AppTwoFactor\Entity\AuthenticatorApp;
use FwsDoctrineAuth\AppTwoFactor\Entity\RecoveryCode;

/**
 * Reads use scalar queries and changes use DQL updates, so values are never stale from the entity manager's cache,
 * and using a code is a single atomic update.
 */
final class DoctrineAuthenticatorAppStore implements AuthenticatorAppStoreInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getSecret(string $userId): ?string
    {
        return $this->getApp($userId)['secret'] ?? null;
    }

    public function getLastTimestep(string $userId): ?int
    {
        $timestep = $this->getApp($userId)['lastTimestep'] ?? null;

        return $timestep === null ? null : (int) $timestep;
    }

    /**
     * @return array{secret: string, lastTimestep: int|string|null}|null
     */
    private function getApp(string $userId): ?array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a.secret, a.lastTimestep')
            ->from(AuthenticatorApp::class, 'a')
            ->where('a.userId = :user')
            ->setParameter('user', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function pair(string $userId, string $secret, int $timestep, DateTimeImmutable $now): void
    {
        $this->unpair($userId);

        /** A managed copy of the old row would clash with the new one */
        $managed = $this->entityManager->getUnitOfWork()->tryGetById($userId, AuthenticatorApp::class);
        if ($managed !== false) {
            $this->entityManager->detach($managed);
        }

        $this->entityManager->persist(new AuthenticatorApp($userId, $secret, $timestep, $now));
        $this->entityManager->flush();
    }

    public function useTimestep(string $userId, int $timestep): bool
    {
        $updated = $this->entityManager->createQueryBuilder()
            ->update(AuthenticatorApp::class, 'a')
            ->set('a.lastTimestep', ':timestep')
            ->where('a.userId = :user AND (a.lastTimestep IS NULL OR a.lastTimestep < :timestep)')
            ->setParameter('user', $userId)
            ->setParameter('timestep', $timestep)
            ->getQuery()
            ->execute();

        return $updated === 1;
    }

    public function unpair(string $userId): void
    {
        $this->deleteRecoveryCodes($userId);
        $this->entityManager->createQueryBuilder()
            ->delete(AuthenticatorApp::class, 'a')
            ->where('a.userId = :user')
            ->setParameter('user', $userId)
            ->getQuery()
            ->execute();
    }

    public function replaceRecoveryCodes(string $userId, array $codeHashes, DateTimeImmutable $now): void
    {
        $this->deleteRecoveryCodes($userId);
        foreach ($codeHashes as $codeHash) {
            $this->entityManager->persist(new RecoveryCode($userId, $codeHash, $now));
        }

        $this->entityManager->flush();
    }

    private function deleteRecoveryCodes(string $userId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(RecoveryCode::class, 'r')
            ->where('r.userId = :user')
            ->setParameter('user', $userId)
            ->getQuery()
            ->execute();
    }

    public function useRecoveryCode(string $userId, string $codeHash, DateTimeImmutable $now): bool
    {
        $updated = $this->entityManager->createQueryBuilder()
            ->update(RecoveryCode::class, 'r')
            ->set('r.usedAt', ':now')
            ->where('r.userId = :user AND r.codeHash = :hash AND r.usedAt IS NULL')
            ->setParameter('user', $userId)
            ->setParameter('hash', $codeHash)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();

        return $updated === 1;
    }

    public function countRecoveryCodes(string $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r)')
            ->from(RecoveryCode::class, 'r')
            ->where('r.userId = :user AND r.usedAt IS NULL')
            ->setParameter('user', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

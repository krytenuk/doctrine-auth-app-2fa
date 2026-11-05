<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Doctrine;

use DateTimeImmutable;
use FwsDoctrineAuth\AppTwoFactor\Doctrine\DoctrineAuthenticatorAppStore;
use FwsDoctrineAuth\AppTwoFactor\Entity\RecoveryCode;
use FwsDoctrineAuthTest\AppTwoFactor\Support\DoctrineTestCase;

final class DoctrineAuthenticatorAppStoreTest extends DoctrineTestCase
{
    private DoctrineAuthenticatorAppStore $store;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DoctrineAuthenticatorAppStore($this->entityManager);
        $this->now   = new DateTimeImmutable('2026-01-01 12:00:00');
    }

    public function testAPairedAppCanBeReadBack(): void
    {
        self::assertNull($this->store->getSecret('7'));

        $this->store->pair('7', 'SECRET', 100, $this->now);

        self::assertSame('SECRET', $this->store->getSecret('7'));
        self::assertSame(100, $this->store->getLastTimestep('7'));
        self::assertNull($this->store->getSecret('8'));
    }

    public function testPairingAgainReplacesTheAppAndItsRecoveryCodes(): void
    {
        $this->store->pair('7', 'OLD', 100, $this->now);
        $this->store->replaceRecoveryCodes('7', ['a', 'b'], $this->now);

        $this->store->pair('7', 'NEW', 50, $this->now);

        self::assertSame('NEW', $this->store->getSecret('7'));
        self::assertSame(50, $this->store->getLastTimestep('7'));
        self::assertSame(0, $this->store->countRecoveryCodes('7'));
    }

    public function testEachTimestepCanOnlyBeUsedOnce(): void
    {
        $this->store->pair('7', 'SECRET', 100, $this->now);

        self::assertTrue($this->store->useTimestep('7', 101));
        self::assertFalse($this->store->useTimestep('7', 101));
        self::assertFalse($this->store->useTimestep('7', 100));
        self::assertSame(101, $this->store->getLastTimestep('7'));
        self::assertFalse($this->store->useTimestep('8', 200), 'Not paired');
    }

    public function testEachRecoveryCodeCanOnlyBeUsedOnce(): void
    {
        $this->store->pair('7', 'SECRET', 100, $this->now);
        $this->store->replaceRecoveryCodes('7', ['a', 'b'], $this->now);

        self::assertTrue($this->store->useRecoveryCode('7', 'a', $this->now));
        self::assertFalse($this->store->useRecoveryCode('7', 'a', $this->now));
        self::assertFalse($this->store->useRecoveryCode('8', 'b', $this->now), 'Another user\'s code');
        self::assertSame(1, $this->store->countRecoveryCodes('7'));
    }

    public function testNewRecoveryCodesReplaceTheOldOnes(): void
    {
        $this->store->replaceRecoveryCodes('7', ['a', 'b'], $this->now);
        $this->store->replaceRecoveryCodes('7', ['c'], $this->now);

        self::assertFalse($this->store->useRecoveryCode('7', 'a', $this->now));
        self::assertTrue($this->store->useRecoveryCode('7', 'c', $this->now));
    }

    public function testUnpairingRemovesEverything(): void
    {
        $this->store->pair('7', 'SECRET', 100, $this->now);
        $this->store->replaceRecoveryCodes('7', ['a'], $this->now);
        $this->store->pair('8', 'OTHER', 100, $this->now);

        $this->store->unpair('7');

        self::assertNull($this->store->getSecret('7'));
        self::assertSame(0, $this->entityManager->getRepository(RecoveryCode::class)->count(['userId' => '7']));
        self::assertSame('OTHER', $this->store->getSecret('8'));
    }
}

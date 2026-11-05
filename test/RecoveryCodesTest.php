<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\RecoveryCodes;
use PHPUnit\Framework\TestCase;

use function array_unique;

final class RecoveryCodesTest extends TestCase
{
    public function testCodesAreUniqueAndReadable(): void
    {
        $codes = RecoveryCodes::generate(10);

        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[2-9a-hjkmnp-z]{5}-[2-9a-hjkmnp-z]{5}$/', $code);
        }
    }

    public function testCaseSpacesAndDashesDoNotMatter(): void
    {
        self::assertSame('7kx2mp9qrt', RecoveryCodes::normalise(' 7KX2M p9qrt '));
        self::assertSame(RecoveryCodes::hash('7kx2m-p9qrt'), RecoveryCodes::hash('7KX2MP9QRT'));
    }

    public function testThingsThatCannotBeCodesAreRejected(): void
    {
        self::assertNull(RecoveryCodes::normalise('123456'));
        self::assertNull(RecoveryCodes::normalise('7kx2m-p9qr'));
        self::assertNull(RecoveryCodes::normalise('7kx2m-p9qr0'), 'Zero is not in the alphabet');
    }
}

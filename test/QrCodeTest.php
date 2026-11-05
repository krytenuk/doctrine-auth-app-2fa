<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\QrCode;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    public function testMakesInlineSvg(): void
    {
        $svg = (new QrCode())->svg('otpauth://totp/My%20Site:user%40example.com?secret=JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringNotContainsString('<?xml', $svg);
    }
}

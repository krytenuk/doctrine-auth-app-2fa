<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\Totp;
use FwsDoctrineAuthTest\AppTwoFactor\Support\Codes;
use FwsDoctrineAuthTest\AppTwoFactor\Support\FakeClock;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    private FakeClock $clock;
    private Totp $totp;
    private string $secret;

    protected function setUp(): void
    {
        $this->clock  = new FakeClock();
        $this->totp   = new Totp($this->clock);
        $this->secret = $this->totp->generateSecret();
    }

    public function testSecretsAre160BitsOfBase32(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $this->secret);
        self::assertNotSame($this->secret, $this->totp->generateSecret());
    }

    public function testTheCurrentCodeIsAccepted(): void
    {
        $now = $this->clock->timestep();

        self::assertSame($now, $this->totp->verify($this->secret, Codes::at($this->secret, $now)));
    }

    public function testCodesOneStepEitherSideAreAccepted(): void
    {
        $now = $this->clock->timestep();

        self::assertSame($now - 1, $this->totp->verify($this->secret, Codes::at($this->secret, $now - 1)));
        self::assertSame($now + 1, $this->totp->verify($this->secret, Codes::at($this->secret, $now + 1)));
    }

    public function testCodesOutsideTheWindowAreRefused(): void
    {
        $now = $this->clock->timestep();

        self::assertNull($this->totp->verify($this->secret, Codes::at($this->secret, $now - 2)));
        self::assertNull($this->totp->verify($this->secret, Codes::at($this->secret, $now + 2)));
    }

    public function testTheWindowCanBeWidened(): void
    {
        $totp = new Totp($this->clock, 2);
        $now  = $this->clock->timestep();

        self::assertSame($now - 2, $totp->verify($this->secret, Codes::at($this->secret, $now - 2)));
    }

    public function testACodeFromAStepAlreadyUsedIsRefused(): void
    {
        $now  = $this->clock->timestep();
        $code = Codes::at($this->secret, $now);

        self::assertNull($this->totp->verify($this->secret, $code, $now));
        self::assertNull($this->totp->verify($this->secret, Codes::at($this->secret, $now - 1), $now));
        self::assertSame($now + 1, $this->totp->verify($this->secret, Codes::at($this->secret, $now + 1), $now));
    }

    public function testWrongCodesAndBadSecretsAreRefused(): void
    {
        $code = Codes::at($this->secret, $this->clock->timestep());

        self::assertNull($this->totp->verify($this->totp->generateSecret(), $code));
        self::assertNull($this->totp->verify('not base32!', $code));
        self::assertNull($this->totp->verify($this->secret, 'abcdef'));
    }

    public function testTheUriIsWhatAppsExpect(): void
    {
        $uri = $this->totp->uri('My Site', 'user@example.com', 'JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('otpauth://totp/My%20Site:user%40example.com?secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('&issuer=My%20Site', $uri);
        self::assertStringContainsString('&digits=6', $uri);
        self::assertStringContainsString('&period=30', $uri);
    }
}

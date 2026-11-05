<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use PragmaRX\Google2FA\Exceptions\Google2FAException;
use PragmaRX\Google2FA\Google2FA;
use Psr\Clock\ClockInterface;

use function intdiv;
use function is_int;

/**
 * Time based codes (RFC 6238), as shown by Google Authenticator, Microsoft Authenticator, 1Password and the like.
 * Codes are 6 digits and change every 30 seconds.
 */
final class Totp
{
    private const PERIOD = 30;

    private readonly Google2FA $google2fa;

    /**
     * @param int $window How many 30 second steps either side of now to accept, to allow for clocks that are a little out
     */
    public function __construct(private readonly ClockInterface $clock, private readonly int $window = 1)
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * A new random secret, 160 bits as RFC 4226 recommends, in the base 32 that apps expect.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * The otpauth:// link an app reads from the QR code.
     *
     * @param string $issuer Your site's name, shown in the app
     * @param string $account Which of the user's accounts this is, such as their email address
     */
    public function uri(string $issuer, string $account, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($issuer, $account, $secret);
    }

    /**
     * The 30 second time step the code belongs to, or null if it is wrong.
     *
     * @param int|null $afterTimestep Only accept codes from later steps than this, so a code cannot be used twice
     */
    public function verify(string $secret, string $code, ?int $afterTimestep = null): ?int
    {
        $now = intdiv($this->clock->now()->getTimestamp(), self::PERIOD);

        try {
            /** Passing an earlier step makes Google2FA return the matching step, rather than true */
            $timestep = $this->google2fa->verifyKey($secret, $code, $this->window, $now, $afterTimestep ?? -1);
        } catch (Google2FAException) {
            return null;
        }

        return is_int($timestep) ? $timestep : null;
    }
}

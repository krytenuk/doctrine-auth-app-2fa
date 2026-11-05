<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

/**
 * What a user needs to add your site to their authenticator app.
 */
final class Pairing
{
    /**
     * @param string $secret For users who cannot scan the QR code and type it in instead
     * @param string $uri The otpauth:// link to put in the QR code
     */
    public function __construct(
        public readonly string $secret,
        public readonly string $uri,
    ) {
    }
}

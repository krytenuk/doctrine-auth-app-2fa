<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Service;

use FwsDoctrineAuth\AppTwoFactor\Totp;
use FwsDoctrineAuth\Clock\SystemClock;
use Psr\Container\ContainerInterface;

final class TotpFactory
{
    public function __invoke(ContainerInterface $container): Totp
    {
        return new Totp(
            $container->get(SystemClock::class),
            (int) ($container->get('config')['doctrine-auth']['app_2fa']['window'] ?? 1),
        );
    }
}

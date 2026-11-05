<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Service;

use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppMethod;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppStoreInterface;
use FwsDoctrineAuth\AppTwoFactor\Totp;
use FwsDoctrineAuth\Clock\SystemClock;
use FwsDoctrineAuth\Event\AuthEventDispatcher;
use FwsDoctrineAuth\User\UserProviderInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class AuthenticatorAppMethodFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthenticatorAppMethod
    {
        return new AuthenticatorAppMethod(
            $container->get(UserProviderInterface::class),
            $container->get(AuthenticatorAppStoreInterface::class),
            $container->get(Totp::class),
            $container->get(AuthEventDispatcher::class),
            $container->get(SystemClock::class),
        );
    }
}

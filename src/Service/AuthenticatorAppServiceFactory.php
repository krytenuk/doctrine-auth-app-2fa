<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Service;

use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppService;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppStoreInterface;
use FwsDoctrineAuth\AppTwoFactor\Totp;
use FwsDoctrineAuth\Clock\SystemClock;
use FwsDoctrineAuth\Event\AuthEventDispatcher;
use FwsDoctrineAuth\Exception\DoctrineAuthException;
use FwsDoctrineAuth\User\UserProviderInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use Psr\Container\NotFoundExceptionInterface;
use function is_string;

final class AuthenticatorAppServiceFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DoctrineAuthException
     */
    public function __invoke(ContainerInterface $container): AuthenticatorAppService
    {
        $config = $container->get('config')['doctrine-auth']['app_2fa'] ?? [];
        $issuer = $config['issuer'] ?? null;
        if (! is_string($issuer) || $issuer === '') {
            throw new DoctrineAuthException(
                'Set doctrine-auth.app_2fa.issuer to your site\'s name, which users will see in their authenticator app'
            );
        }

        return new AuthenticatorAppService(
            $container->get(UserProviderInterface::class),
            $container->get(AuthenticatorAppStoreInterface::class),
            $container->get(Totp::class),
            $container->get(AuthEventDispatcher::class),
            $container->get(SystemClock::class),
            $issuer,
            (int) ($config['recovery_codes'] ?? 10),
        );
    }
}

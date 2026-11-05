<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor\Service;

use FwsDoctrineAuth\AppTwoFactor\Doctrine\DoctrineAuthenticatorAppStore;
use FwsDoctrineAuth\Service\EntityManagerLocator;
use Psr\Container\ContainerInterface;

final class DoctrineAuthenticatorAppStoreFactory
{
    public function __invoke(ContainerInterface $container): DoctrineAuthenticatorAppStore
    {
        return new DoctrineAuthenticatorAppStore(EntityManagerLocator::get($container));
    }
}

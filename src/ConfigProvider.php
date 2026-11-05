<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\Doctrine\DoctrineAuthenticatorAppStore;

/**
 * Adds the authenticator app to the core module's two factor methods, under the name "app".
 */
final class ConfigProvider
{
    public const METHOD_NAME = 'app';

    public function __invoke(): array
    {
        return [
            'dependencies'  => $this->getDependencies(),
            'doctrine'      => $this->getDoctrineConfig(),
            'doctrine-auth' => $this->getDefaults(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'    => [
                AuthenticatorAppStoreInterface::class => DoctrineAuthenticatorAppStore::class,
            ],
            'invokables' => [
                QrCode::class => QrCode::class,
            ],
            'factories'  => [
                AuthenticatorAppMethod::class        => Service\AuthenticatorAppMethodFactory::class,
                AuthenticatorAppService::class       => Service\AuthenticatorAppServiceFactory::class,
                DoctrineAuthenticatorAppStore::class => Service\DoctrineAuthenticatorAppStoreFactory::class,
                Totp::class                          => Service\TotpFactory::class,
            ],
        ];
    }

    /**
     * Adds the entities to the default entity manager's attribute driver.
     */
    public function getDoctrineConfig(): array
    {
        return [
            'driver' => [
                'orm_default' => [
                    'paths' => [__DIR__ . '/Entity'],
                ],
            ],
        ];
    }

    public function getDefaults(): array
    {
        return [
            'two_factor' => [
                'methods' => [
                    self::METHOD_NAME => AuthenticatorAppMethod::class,
                ],
            ],
            'app_2fa'    => [
                'window'         => 1,
                'recovery_codes' => 10,
            ],
        ];
    }
}

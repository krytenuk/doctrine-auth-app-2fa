<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function sys_get_temp_dir;

/**
 * Runs against a real SQLite database, as the queries themselves are what these tests need to check.
 */
abstract class DoctrineTestCase extends TestCase
{
    protected EntityManager $entityManager;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Needs pdo_sqlite. Run the tests in Docker, as the README describes');
        }

        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/../../src/Entity']));
        $configuration->setProxyDir(sys_get_temp_dir());
        $configuration->setProxyNamespace('FwsDoctrineAuthAppTwoFactorTestProxies');

        $this->entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration),
            $configuration
        );

        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }
}

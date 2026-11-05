<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Integration;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppService;
use FwsDoctrineAuth\AppTwoFactor\ConfigProvider;
use FwsDoctrineAuth\AppTwoFactor\Entity\AuthenticatorApp;
use FwsDoctrineAuth\AppTwoFactor\Entity\RecoveryCode;
use FwsDoctrineAuth\AppTwoFactor\QrCode;
use FwsDoctrineAuth\ConfigProvider as CoreConfigProvider;
use FwsDoctrineAuth\Entity\FailedLoginAttemptsLog;
use FwsDoctrineAuth\Entity\IpBlocked;
use FwsDoctrineAuth\Entity\LoginLog;
use FwsDoctrineAuth\Exception\DoctrineAuthException;
use FwsDoctrineAuth\Login\LoginService;
use FwsDoctrineAuth\Mezzio\SessionLogin;
use FwsDoctrineAuth\Mezzio\TwoFactorLogin;
use FwsDoctrineAuth\TwoFactor\TwoFactorStatus;
use FwsDoctrineAuthTest\AppTwoFactor\Support\Codes;
use FwsDoctrineAuthTest\AppTwoFactor\Support\Entity\User;
use Laminas\Diactoros\ResponseFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Mezzio\Authentication\ConfigProvider as AuthenticationConfigProvider;
use Mezzio\Session\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Roave\PsrContainerDoctrine\EntityManagerFactory;
use Throwable;

use function intdiv;
use function password_hash;
use function sys_get_temp_dir;
use function time;

use const PASSWORD_BCRYPT;

/**
 * Builds the core module and this plugin the way a Mezzio application does, then logs in with the app.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class ContainerWiringTest extends TestCase
{
    private const ENTITY_MANAGER = 'doctrine.entity_manager.orm_default';
    private const IP             = '203.0.113.5';

    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function configOrders(): iterable
    {
        yield 'plugin after core' => [false];
        yield 'plugin before core' => [true];
    }

    private function container(bool $pluginFirst = false, array $appConfig = []): ServiceManager
    {
        $providers = [(new CoreConfigProvider())(), (new ConfigProvider())()];
        if ($pluginFirst) {
            $providers = [$providers[1], $providers[0]];
        }

        $config = (new AuthenticationConfigProvider())();
        foreach ($providers as $provider) {
            $config = ArrayUtils::merge($config, $provider);
        }

        $config = ArrayUtils::merge($config, [
            'doctrine'      => [
                'connection'    => [
                    'orm_default' => ['driver_class' => Driver::class, 'params' => ['memory' => true]],
                ],
                'configuration' => [
                    'orm_default' => ['proxy_dir' => sys_get_temp_dir(), 'auto_generate_proxy_classes' => true],
                ],
                'driver'        => [
                    'orm_default' => ['class' => AttributeDriver::class, 'paths' => [__DIR__ . '/../Support/Entity']],
                ],
            ],
            'doctrine-auth' => [
                'user_class' => User::class,
                'throttle'   => ['secret' => 'test-secret'],
                'app_2fa'    => ['issuer' => 'My Site'],
            ],
        ]);
        $config = ArrayUtils::merge($config, $appConfig);

        $dependencies                                                = $config['dependencies'];
        $dependencies['factories'][self::ENTITY_MANAGER]             = EntityManagerFactory::class;
        $dependencies['aliases'][EntityManagerInterface::class]      = self::ENTITY_MANAGER;
        $dependencies['invokables'][ResponseFactoryInterface::class] = ResponseFactory::class;
        $dependencies['services']['config']                          = $config;

        return new ServiceManager($dependencies);
    }

    private function createSchema(ServiceManager $container): EntityManagerInterface
    {
        $entityManager = $container->get(self::ENTITY_MANAGER);
        (new SchemaTool($entityManager))->createSchema(array_map(
            $entityManager->getClassMetadata(...),
            [User::class, LoginLog::class, FailedLoginAttemptsLog::class, IpBlocked::class,
                AuthenticatorApp::class, RecoveryCode::class]
        ));

        return $entityManager;
    }

    private function currentCode(string $secret): string
    {
        return Codes::at($secret, intdiv(time(), 30));
    }

    #[DataProvider('configOrders')]
    public function testPairingAndLoggingInWithTheApp(bool $pluginFirst): void
    {
        $container     = $this->container($pluginFirst);
        $entityManager = $this->createSchema($container);
        $user          = new User('a@example.com', password_hash('correct', PASSWORD_BCRYPT, ['cost' => 4]));
        $entityManager->persist($user);
        $entityManager->flush();

        $login = $container->get(LoginService::class);
        self::assertTrue($login->attempt('a@example.com', 'correct', self::IP)->isSuccess(), 'No app paired yet');

        $apps    = $container->get(AuthenticatorAppService::class);
        $session = new Session([]);
        $pairing = $apps->startPairing($session, $user);
        self::assertStringStartsWith('<svg', $container->get(QrCode::class)->svg($pairing->uri));
        $recoveryCodes = $apps->confirmPairing($session, $user, $this->currentCode($pairing->secret));
        self::assertNotNull($recoveryCodes);

        $result = $login->attempt('a@example.com', 'correct', self::IP);
        self::assertTrue($result->requiresTwoFactor());

        $twoFactor = $container->get(TwoFactorLogin::class);
        $session   = $twoFactor->begin(new Session([]), $result->user);
        self::assertSame([ConfigProvider::METHOD_NAME], $twoFactor->availableMethods($session));

        $wrong = $twoFactor->verify($session, ConfigProvider::METHOD_NAME, '000000', self::IP);
        self::assertSame(TwoFactorStatus::InvalidCode, $wrong->status);
        self::assertSame(1, $entityManager->getRepository(FailedLoginAttemptsLog::class)->count([]));

        $verified = $twoFactor->verify($session, ConfigProvider::METHOD_NAME, $recoveryCodes[0], self::IP);
        self::assertTrue($verified->isSuccess());
        self::assertSame((string) $user->id, $verified->session?->get(SessionLogin::SESSION_KEY));
        self::assertSame(0, $entityManager->getRepository(FailedLoginAttemptsLog::class)->count([]));
        self::assertSame(2, $entityManager->getRepository(LoginLog::class)->count([]));
    }

    public function testTheMethodCanBeTurnedOff(): void
    {
        $container = $this->container(appConfig: ['doctrine-auth' => ['two_factor' => ['methods' => [
            ConfigProvider::METHOD_NAME => null,
        ]]]]);

        self::assertSame([], $container->get(\FwsDoctrineAuth\TwoFactor\TwoFactorMethods::class)->names());
    }

    public function testAMissingIssuerFailsClearly(): void
    {
        $container = $this->container(appConfig: ['doctrine-auth' => ['app_2fa' => ['issuer' => '']]]);

        try {
            $container->get(AuthenticatorAppService::class);
            self::fail('Expected an exception');
        } catch (Throwable $exception) {
            while ($exception->getPrevious() !== null) {
                $exception = $exception->getPrevious();
            }

            self::assertInstanceOf(DoctrineAuthException::class, $exception);
            self::assertStringContainsString('app_2fa.issuer', $exception->getMessage());
        }
    }

    public function testConfigCanBeCached(): void
    {
        $config = (new ConfigProvider())();

        array_walk_recursive($config, static function (mixed $value): void {
            self::assertIsNotObject($value);
        });
    }
}

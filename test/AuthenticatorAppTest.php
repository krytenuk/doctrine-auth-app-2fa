<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor;

use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppMethod;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppService;
use FwsDoctrineAuth\AppTwoFactor\Doctrine\DoctrineAuthenticatorAppStore;
use FwsDoctrineAuth\AppTwoFactor\Event\AuthenticatorAppPaired;
use FwsDoctrineAuth\AppTwoFactor\Event\AuthenticatorAppRemoved;
use FwsDoctrineAuth\AppTwoFactor\Event\RecoveryCodesRegenerated;
use FwsDoctrineAuth\AppTwoFactor\Event\RecoveryCodeUsed;
use FwsDoctrineAuth\AppTwoFactor\Totp;
use FwsDoctrineAuth\Exception\DoctrineAuthException;
use FwsDoctrineAuthTest\AppTwoFactor\Support\Codes;
use FwsDoctrineAuthTest\AppTwoFactor\Support\DoctrineTestCase;
use FwsDoctrineAuthTest\AppTwoFactor\Support\FakeClock;
use FwsDoctrineAuthTest\AppTwoFactor\Support\FakeUser;
use FwsDoctrineAuthTest\AppTwoFactor\Support\InMemoryUserProvider;
use FwsDoctrineAuthTest\AppTwoFactor\Support\RecordingDispatcher;
use Mezzio\Session\Session;

/**
 * Pairing through AuthenticatorAppService, then logging in through AuthenticatorAppMethod.
 */
final class AuthenticatorAppTest extends DoctrineTestCase
{
    private FakeUser $user;
    private FakeUser $other;
    private FakeClock $clock;
    private RecordingDispatcher $events;
    private AuthenticatorAppService $service;
    private AuthenticatorAppMethod $method;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user    = new FakeUser('7', 'user@example.com');
        $this->other   = new FakeUser('8', 'other@example.com');
        $users         = new InMemoryUserProvider($this->user, $this->other);
        $store         = new DoctrineAuthenticatorAppStore($this->entityManager);
        $this->clock   = new FakeClock();
        $totp          = new Totp($this->clock);
        $this->events  = new RecordingDispatcher();
        $this->service = new AuthenticatorAppService($users, $store, $totp, $this->events, $this->clock, 'My Site', 10);
        $this->method  = new AuthenticatorAppMethod($users, $store, $totp, $this->events, $this->clock);
        $this->session = new Session([]);
    }

    private function currentCode(string $secret): string
    {
        return Codes::at($secret, $this->clock->timestep());
    }

    /**
     * @return array{0: string, 1: list<string>} The secret and the recovery codes
     */
    private function pair(): array
    {
        $secret = $this->service->startPairing($this->session, $this->user)->secret;
        $codes  = $this->service->confirmPairing($this->session, $this->user, $this->currentCode($secret));
        self::assertNotNull($codes);
        $this->events->events = [];

        return [$secret, $codes];
    }

    public function testStartingPairingSavesNothing(): void
    {
        $pairing = $this->service->startPairing($this->session, $this->user);

        self::assertStringStartsWith('otpauth://totp/My%20Site:user%40example.com?secret=' . $pairing->secret, $pairing->uri);
        self::assertFalse($this->service->isPaired($this->user));
        self::assertFalse($this->method->isEnabledFor($this->user));
    }

    public function testShowingThePageAgainKeepsTheSameSecret(): void
    {
        $first = $this->service->startPairing($this->session, $this->user)->secret;

        self::assertSame($first, $this->service->startPairing($this->session, $this->user)->secret);
        self::assertNotSame($first, $this->service->startPairing($this->session, $this->user, true)->secret);
    }

    public function testTheRightCodeCompletesPairing(): void
    {
        $secret = $this->service->startPairing($this->session, $this->user)->secret;

        $codes = $this->service->confirmPairing($this->session, $this->user, $this->currentCode($secret));

        self::assertCount(10, $codes ?? []);
        self::assertTrue($this->service->isPaired($this->user));
        self::assertTrue($this->method->isEnabledFor($this->user));
        self::assertSame(10, $this->service->countRecoveryCodes($this->user));
        self::assertFalse($this->session->has(AuthenticatorAppService::SESSION_KEY));
        self::assertSame([AuthenticatorAppPaired::class], $this->events->classes());
    }

    public function testAWrongCodeLeavesPairingOpenToTryAgain(): void
    {
        $secret = $this->service->startPairing($this->session, $this->user)->secret;

        self::assertNull($this->service->confirmPairing($this->session, $this->user, '000000'));
        self::assertFalse($this->service->isPaired($this->user));
        self::assertNotNull($this->service->confirmPairing($this->session, $this->user, $this->currentCode($secret)));
    }

    public function testPairingCannotBeConfirmedWithoutStarting(): void
    {
        self::assertNull($this->service->confirmPairing($this->session, $this->user, '123456'));
    }

    public function testAnotherUserCannotConfirmAPairingStartedInTheSameSession(): void
    {
        $secret = $this->service->startPairing($this->session, $this->user)->secret;

        self::assertNull($this->service->confirmPairing($this->session, $this->other, $this->currentCode($secret)));
        self::assertFalse($this->service->isPaired($this->other));
    }

    public function testTheAppsCodeLogsIn(): void
    {
        [$secret] = $this->pair();
        $this->clock->advance(30);

        self::assertTrue($this->method->verify($this->user, $this->currentCode($secret)));
    }

    public function testSpacesInTheCodeAreIgnored(): void
    {
        [$secret] = $this->pair();
        $this->clock->advance(30);
        $code = $this->currentCode($secret);

        self::assertTrue($this->method->verify($this->user, ' ' . substr($code, 0, 3) . ' ' . substr($code, 3)));
    }

    public function testTheCodeUsedToPairCannotBeUsedToLogIn(): void
    {
        [$secret] = $this->pair();

        self::assertFalse($this->method->verify($this->user, $this->currentCode($secret)));
    }

    public function testACodeCannotBeUsedTwice(): void
    {
        [$secret] = $this->pair();
        $this->clock->advance(30);
        $code = $this->currentCode($secret);

        self::assertTrue($this->method->verify($this->user, $code));
        self::assertFalse($this->method->verify($this->user, $code));
    }

    public function testAnOlderCodeCannotBeUsedAfterANewerOne(): void
    {
        [$secret] = $this->pair();
        $this->clock->advance(60);
        $older = Codes::at($secret, $this->clock->timestep() - 1);

        self::assertTrue($this->method->verify($this->user, $this->currentCode($secret)));
        self::assertFalse($this->method->verify($this->user, $older));
    }

    public function testAWrongCodeIsRefused(): void
    {
        $this->pair();
        $this->clock->advance(30);

        self::assertFalse($this->method->verify($this->user, '000000'));
        self::assertFalse($this->method->verify($this->user, ''));
    }

    public function testAnUnpairedUserCannotLogInWithTheMethod(): void
    {
        self::assertFalse($this->method->isEnabledFor($this->other));
        self::assertFalse($this->method->verify($this->other, '123456'));
    }

    public function testARecoveryCodeLogsInOnce(): void
    {
        [, $codes] = $this->pair();

        self::assertTrue($this->method->verify($this->user, strtoupper($codes[0])));
        self::assertFalse($this->method->verify($this->user, $codes[0]));
        self::assertSame([RecoveryCodeUsed::class], $this->events->classes());
        self::assertSame(9, $this->events->events[0]->remaining);
    }

    public function testRecoveryCodesBelongToOneUser(): void
    {
        [, $codes] = $this->pair();
        $otherSession = new Session([]);
        $secret       = $this->service->startPairing($otherSession, $this->other)->secret;
        $this->service->confirmPairing($otherSession, $this->other, $this->currentCode($secret));

        self::assertFalse($this->method->verify($this->other, $codes[0]));
    }

    public function testRegeneratingRecoveryCodesStopsTheOldOnesWorking(): void
    {
        [, $old] = $this->pair();

        $new = $this->service->regenerateRecoveryCodes($this->user);

        self::assertFalse($this->method->verify($this->user, $old[0]));
        self::assertTrue($this->method->verify($this->user, $new[0]));
        self::assertSame(RecoveryCodesRegenerated::class, $this->events->classes()[0]);
    }

    public function testRecoveryCodesNeedAPairedApp(): void
    {
        $this->expectException(DoctrineAuthException::class);

        $this->service->regenerateRecoveryCodes($this->user);
    }

    public function testUnpairingTurnsTheMethodOff(): void
    {
        [, $codes] = $this->pair();

        $this->service->unpair($this->user);
        $this->service->unpair($this->user);

        self::assertFalse($this->method->isEnabledFor($this->user));
        self::assertFalse($this->method->verify($this->user, $codes[0]));
        self::assertSame([AuthenticatorAppRemoved::class], $this->events->classes(), 'Only one event');
    }

    public function testPairingANewPhoneReplacesTheOldOne(): void
    {
        [$oldSecret, $oldCodes] = $this->pair();
        $this->clock->advance(30);

        $newSecret = $this->service->startPairing($this->session, $this->user)->secret;
        $this->service->confirmPairing($this->session, $this->user, $this->currentCode($newSecret));
        $this->clock->advance(30);

        self::assertNotSame($oldSecret, $newSecret);
        self::assertFalse($this->method->verify($this->user, $this->currentCode($oldSecret)));
        self::assertFalse($this->method->verify($this->user, $oldCodes[0]));
        self::assertTrue($this->method->verify($this->user, $this->currentCode($newSecret)));
    }
}

<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support;

use FwsDoctrineAuth\User\UserProviderInterface;

use function array_filter;

final class InMemoryUserProvider implements UserProviderInterface
{
    /** @var list<FakeUser> */
    private array $users;

    public function __construct(FakeUser ...$users)
    {
        $this->users = $users;
    }

    public function findByIdentity(string $identity): iterable
    {
        return array_filter($this->users, static fn (FakeUser $user): bool => $user->identity === $identity);
    }

    public function findById(string $id): ?object
    {
        foreach ($this->users as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }

        return null;
    }

    public function getId(object $user): string
    {
        return $user->id;
    }

    public function getIdentity(object $user): string
    {
        return $user->identity;
    }

    public function getPasswordHash(object $user): ?string
    {
        return $user->hash;
    }

    public function setPasswordHash(object $user, string $hash): void
    {
        $user->hash = $hash;
    }

    public function isEnabled(object $user): bool
    {
        return $user->enabled;
    }
}

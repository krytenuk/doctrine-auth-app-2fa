<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support;

final class FakeUser
{
    public function __construct(
        public string $id = '7',
        public string $identity = 'user@example.com',
        public ?string $hash = 'hash',
        public bool $enabled = true,
    ) {
    }
}

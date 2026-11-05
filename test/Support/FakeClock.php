<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FakeClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-01-01 12:00:00'))
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify('+' . $seconds . ' seconds');
    }

    public function timestep(): int
    {
        return intdiv($this->now->getTimestamp(), 30);
    }
}

<?php

declare(strict_types=1);

namespace FwsDoctrineAuthTest\AppTwoFactor\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

use function array_map;

final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<class-string>
     */
    public function classes(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->events);
    }
}

<?php

namespace AppRadar\Agent\Laravel\Support;

use Illuminate\Console\Scheduling\Event;

class SchedulerTaskDescriptor
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
    ) {
    }

    public static function fromEvent(Event $event): self
    {
        return new self(
            key: $event->mutexName(),
            name: $event->getSummaryForDisplay(),
        );
    }

    public function isInternalHeartbeat(): bool
    {
        return $this->name === 'app-status-heartbeat';
    }
}

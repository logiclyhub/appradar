<?php

namespace AppRadar\Agent\Laravel\Support;

class SchedulerHeartbeatWriter
{
    public function __construct(
        private readonly StatusFileStore $store = new StatusFileStore(),
    ) {
    }

    public function write(): void
    {
        $this->store->writeJson('scheduler-heartbeat.json', [
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}

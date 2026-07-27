<?php

namespace AppRadar\Agent\Laravel\Support;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;

class QueueEventListener
{
    public function __construct(
        private readonly QueueMetricsStore $store = new QueueMetricsStore(),
    ) {
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $this->store->markProcessed($event->connectionName, (string) $event->job->getQueue());
    }

    public function handleFailed(JobFailed $event): void
    {
        $this->store->markFailed($event->connectionName, (string) $event->job->getQueue());
    }

    public function handleExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->store->markFailed($event->connectionName, (string) $event->job->getQueue());
    }
}

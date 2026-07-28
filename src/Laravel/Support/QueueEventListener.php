<?php

namespace AppRadar\Agent\Laravel\Support;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Throwable;

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
        $this->store->recordIncident(
            connection: $event->connectionName,
            queue: (string) $event->job->getQueue(),
            jobName: $this->jobName($event->job),
            attempt: $this->attempts($event->job),
            exceptionClass: $event->exception::class,
            exceptionMessage: $event->exception->getMessage(),
            timedOut: $this->timedOut($event->exception),
            failed: true,
        );
    }

    public function handleExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->store->markFailed($event->connectionName, (string) $event->job->getQueue());
        $this->store->recordIncident(
            connection: $event->connectionName,
            queue: (string) $event->job->getQueue(),
            jobName: $this->jobName($event->job),
            attempt: $this->attempts($event->job),
            exceptionClass: $event->exception::class,
            exceptionMessage: $event->exception->getMessage(),
            timedOut: $this->timedOut($event->exception),
            failed: false,
        );
    }

    private function jobName(object $job): string
    {
        if (method_exists($job, 'resolveName')) {
            return (string) $job->resolveName();
        }

        return $job::class;
    }

    private function attempts(object $job): ?int
    {
        if (!method_exists($job, 'attempts')) {
            return null;
        }

        return max(0, (int) $job->attempts());
    }

    private function timedOut(Throwable $exception): bool
    {
        return str_contains($exception::class, 'TimeoutExceededException')
            || str_contains(strtolower($exception->getMessage()), 'timed out');
    }
}

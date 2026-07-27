<?php

namespace AppRadar\Agent\Laravel\Support;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;

class SchedulerEventListener
{
    public function __construct(
        private readonly SchedulerMetricsStore $store = new SchedulerMetricsStore(),
    ) {
    }

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $task = SchedulerTaskDescriptor::fromEvent($event->task);

        if ($task->isInternalHeartbeat()) {
            return;
        }

        $this->store->markStarted($task);
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = SchedulerTaskDescriptor::fromEvent($event->task);

        if ($task->isInternalHeartbeat()) {
            return;
        }

        $this->store->markFinished($task, round($event->runtime, 2));
    }

    public function handleBackgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $task = SchedulerTaskDescriptor::fromEvent($event->task);

        if ($task->isInternalHeartbeat()) {
            return;
        }

        if ((int) $event->task->exitCode === 0) {
            $this->store->markFinished($task);

            return;
        }

        $this->store->markFailed($task, 'Background task exited with a non-zero status code.');
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $task = SchedulerTaskDescriptor::fromEvent($event->task);

        if ($task->isInternalHeartbeat()) {
            return;
        }

        $this->store->markFailed($task, $event->exception->getMessage());
    }
}

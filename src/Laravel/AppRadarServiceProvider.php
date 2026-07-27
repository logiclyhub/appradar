<?php

namespace AppRadar\Agent\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use AppRadar\Agent\Laravel\Support\QueueEventListener;
use AppRadar\Agent\Laravel\Support\SchedulerEventListener;

class AppRadarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/appradar.php', 'appradar');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->registerHeartbeatSchedule();

        Event::listen(ScheduledTaskStarting::class, [SchedulerEventListener::class, 'handleStarting']);
        Event::listen(ScheduledTaskFinished::class, [SchedulerEventListener::class, 'handleFinished']);
        Event::listen(ScheduledBackgroundTaskFinished::class, [SchedulerEventListener::class, 'handleBackgroundFinished']);
        Event::listen(ScheduledTaskFailed::class, [SchedulerEventListener::class, 'handleFailed']);

        Event::listen(JobProcessed::class, [QueueEventListener::class, 'handleProcessed']);
        Event::listen(JobFailed::class, [QueueEventListener::class, 'handleFailed']);
        Event::listen(JobExceptionOccurred::class, [QueueEventListener::class, 'handleExceptionOccurred']);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/appradar.php' => config_path('appradar.php'),
            ], 'appradar-config');
        }
    }

    private function registerHeartbeatSchedule(): void
    {
        $register = function (Schedule $schedule): void {
            $schedule->call(function (): void {
                app(Support\SchedulerHeartbeatWriter::class)->write();
            })->everyMinute()->name((string) config('appradar.scheduler.heartbeat_name'))->withoutOverlapping();
        };

        $this->callAfterResolving(Schedule::class, $register);

        if ($this->app->resolved(Schedule::class)) {
            $register($this->app->make(Schedule::class));
        }
    }
}

<?php

namespace AppRadar\Agent\Laravel;

use AppRadar\Agent\Core\Errors\ErrorCapturePolicy;
use AppRadar\Agent\Core\Errors\ErrorFingerprintBuilder;
use AppRadar\Agent\Core\Errors\ErrorIgnoreList;
use AppRadar\Agent\Core\Errors\ErrorIngestClient;
use AppRadar\Agent\Core\Errors\ErrorReportingSettings;
use AppRadar\Agent\Core\Errors\ErrorScrubber;
use AppRadar\Agent\Core\Errors\StacktraceBuilder;
use AppRadar\Agent\Core\Errors\StreamErrorIngestTransport;
use AppRadar\Agent\Laravel\Errors\LaravelErrorEventFactory;
use AppRadar\Agent\Laravel\Errors\LaravelErrorReporter;
use AppRadar\Agent\Laravel\Support\QueueEventListener;
use AppRadar\Agent\Laravel\Support\SchedulerEventListener;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppRadarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/appradar.php', 'appradar');
        $this->registerErrorReportingBindings();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->registerHeartbeatSchedule();
        $this->registerErrorReportingHook();

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

    private function registerErrorReportingBindings(): void
    {
        $this->app->singleton(ErrorReportingSettings::class, function (): ErrorReportingSettings {
            $ignore = config('appradar.errors.ignore');
            $ignoreList = is_array($ignore) && $ignore !== []
                ? new ErrorIgnoreList(array_values(array_map('strval', $ignore)))
                : ErrorIgnoreList::defaults();

            $configuredBaseUrl = trim((string) config('appradar.errors.base_url', ''));
            $baseUrl = $configuredBaseUrl !== ''
                ? rtrim($configuredBaseUrl, '/')
                : \AppRadar\Agent\Core\AppRadarCloud::BASE_URL;

            $secret = trim((string) config('appradar.secret', ''));
            if ($secret === '') {
                $secret = trim((string) config('appradar.status_token', ''));
            }

            return new ErrorReportingSettings(
                baseUrl: $baseUrl,
                appUuid: trim((string) config('appradar.errors.app_uuid', '')),
                secret: $secret,
                sampleRate: (float) config('appradar.errors.sample_rate', 1.0),
                sendTimeoutSeconds: (float) config('appradar.errors.send_timeout_seconds', 2.0),
                environment: ($env = config('appradar.errors.environment')) !== null && $env !== ''
                    ? (string) $env
                    : null,
                release: ($release = config('appradar.errors.release')) !== null && trim((string) $release) !== ''
                    ? trim((string) $release)
                    : null,
                ignoreList: $ignoreList,
            );
        });

        $this->app->singleton(ErrorCapturePolicy::class, function ($app): ErrorCapturePolicy {
            $settings = $app->make(ErrorReportingSettings::class);

            return new ErrorCapturePolicy($settings->ignoreList, $settings->sampleRate);
        });

        $this->app->singleton(ErrorIngestClient::class, function ($app): ErrorIngestClient {
            $settings = $app->make(ErrorReportingSettings::class);

            return new ErrorIngestClient(
                endpoint: $settings->ingestUrl(),
                token: $settings->secret,
                timeoutSeconds: $settings->sendTimeoutSeconds,
                transport: new StreamErrorIngestTransport(),
            );
        });

        $this->app->singleton(LaravelErrorEventFactory::class, function ($app): LaravelErrorEventFactory {
            $settings = $app->make(ErrorReportingSettings::class);

            return new LaravelErrorEventFactory(
                settings: $settings,
                stacktraceBuilder: new StacktraceBuilder(base_path()),
                fingerprintBuilder: new ErrorFingerprintBuilder(),
                scrubber: new ErrorScrubber(),
                environment: (string) $app->environment(),
            );
        });

        $this->app->singleton(LaravelErrorReporter::class, function ($app): LaravelErrorReporter {
            return new LaravelErrorReporter(
                settings: $app->make(ErrorReportingSettings::class),
                capturePolicy: $app->make(ErrorCapturePolicy::class),
                eventFactory: $app->make(LaravelErrorEventFactory::class),
                ingestClient: $app->make(ErrorIngestClient::class),
                app: $app,
            );
        });
    }

    private function registerErrorReportingHook(): void
    {
        $settings = $this->app->make(ErrorReportingSettings::class);
        if (! $settings->isActive()) {
            return;
        }

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! method_exists($handler, 'reportable')) {
                return;
            }

            $handler->reportable(function (Throwable $throwable): void {
                try {
                    $this->app->make(LaravelErrorReporter::class)->report($throwable);
                } catch (Throwable) {
                    // Fail open.
                }
            });
        });
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

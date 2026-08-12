<?php

namespace AppRadar\Agent\Laravel\Errors;

use AppRadar\Agent\Core\Errors\ErrorCapturePolicy;
use AppRadar\Agent\Core\Errors\ErrorIngestClient;
use AppRadar\Agent\Core\Errors\ErrorReportingSettings;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Throwable;

final class LaravelErrorReporter
{
    public function __construct(
        private readonly ErrorReportingSettings $settings,
        private readonly ErrorCapturePolicy $capturePolicy,
        private readonly LaravelErrorEventFactory $eventFactory,
        private readonly ErrorIngestClient $ingestClient,
        private readonly Application $app,
    ) {
    }

    public function report(Throwable $throwable): void
    {
        if (! $this->settings->isActive()) {
            return;
        }

        if (! $this->capturePolicy->shouldCapture($throwable)->shouldCapture) {
            return;
        }

        try {
            $request = $this->currentRequest();
            $fromQueue = $request === null && $this->app->runningInConsole();
            $event = $this->eventFactory->fromThrowable($throwable, $request, $fromQueue);

            if ($this->app->runningInConsole()) {
                $this->ingestClient->send($event);

                return;
            }

            $this->app->terminating(function () use ($event): void {
                $this->ingestClient->send($event);
            });
        } catch (Throwable) {
            // Fail open: never break the host app's error reporting.
        }
    }

    private function currentRequest(): ?Request
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        return $request instanceof Request ? $request : null;
    }
}

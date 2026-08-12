<?php

namespace AppRadar\Agent\Laravel\Errors;

use AppRadar\Agent\Core\Errors\ErrorAppContext;
use AppRadar\Agent\Core\Errors\ErrorEvent;
use AppRadar\Agent\Core\Errors\ErrorExceptionInfo;
use AppRadar\Agent\Core\Errors\ErrorFingerprintBuilder;
use AppRadar\Agent\Core\Errors\ErrorReportingSettings;
use AppRadar\Agent\Core\Errors\ErrorRequestContext;
use AppRadar\Agent\Core\Errors\ErrorScrubber;
use AppRadar\Agent\Core\Errors\StacktraceBuilder;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class LaravelErrorEventFactory
{
    public function __construct(
        private readonly ErrorReportingSettings $settings,
        private readonly StacktraceBuilder $stacktraceBuilder,
        private readonly ErrorFingerprintBuilder $fingerprintBuilder,
        private readonly ErrorScrubber $scrubber,
        private readonly string $environment,
    ) {
    }

    public function fromThrowable(Throwable $throwable, ?Request $request, bool $fromQueue): ErrorEvent
    {
        $type = $throwable::class;
        $stacktrace = $this->stacktraceBuilder->fromThrowable($throwable);
        $fingerprint = $this->fingerprintBuilder->build($type, $stacktrace);
        $now = gmdate('c');

        $requestContext = null;
        if ($request !== null) {
            $requestContext = $this->scrubber->scrubRequest(new ErrorRequestContext(
                method: $request->getMethod(),
                url: $request->fullUrl(),
                headers: [
                    'user-agent' => (string) $request->userAgent(),
                    'accept' => (string) $request->header('accept', ''),
                    'content-type' => (string) $request->header('content-type', ''),
                ],
                context: [],
            ));
        }

        $frameworkVersion = class_exists(LaravelApplication::class)
            ? LaravelApplication::VERSION
            : null;

        return new ErrorEvent(
            schemaVersion: 1,
            sentAt: $now,
            app: new ErrorAppContext(
                environment: $this->settings->environment ?? $this->environment,
                release: $this->settings->release,
                runtime: 'php',
                runtimeVersion: PHP_VERSION,
                framework: 'laravel',
                frameworkVersion: $frameworkVersion,
            ),
            eventId: (string) Str::uuid(),
            timestamp: $now,
            level: 'error',
            fingerprint: $fingerprint,
            exception: new ErrorExceptionInfo(
                type: $type,
                message: $this->scrubber->scrubMessage($throwable->getMessage()),
                stacktrace: $stacktrace,
            ),
            request: $requestContext,
            queue: $fromQueue,
        );
    }
}

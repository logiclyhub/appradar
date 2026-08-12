<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorEvent
{
    public function __construct(
        public readonly int $schemaVersion,
        public readonly string $sentAt,
        public readonly ErrorAppContext $app,
        public readonly string $eventId,
        public readonly string $timestamp,
        public readonly string $level,
        public readonly ErrorFingerprint $fingerprint,
        public readonly ErrorExceptionInfo $exception,
        public readonly ?ErrorRequestContext $request,
        public readonly bool $queue,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'sent_at' => $this->sentAt,
            'app' => $this->app->toArray(),
            'event' => [
                'event_id' => $this->eventId,
                'timestamp' => $this->timestamp,
                'level' => $this->level,
                'fingerprint' => $this->fingerprint->toArray(),
                'exception' => $this->exception->toArray(),
                'request' => $this->request?->toArray(),
                'tags' => [
                    'queue' => $this->queue,
                ],
                'breadcrumbs' => [],
            ],
        ];
    }
}

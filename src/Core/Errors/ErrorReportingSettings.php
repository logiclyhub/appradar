<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorReportingSettings
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $appUuid,
        public readonly string $secret,
        public readonly float $sampleRate,
        public readonly float $sendTimeoutSeconds,
        public readonly ?string $environment,
        public readonly ?string $release,
        public readonly ErrorIgnoreList $ignoreList,
    ) {
    }

    public function isActive(): bool
    {
        return $this->baseUrl !== ''
            && $this->appUuid !== ''
            && $this->secret !== '';
    }

    public function ingestUrl(): string
    {
        return rtrim($this->baseUrl, '/')
            .'/api/agent/apps/'
            .rawurlencode($this->appUuid)
            .'/errors';
    }
}

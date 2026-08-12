<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorAppContext
{
    public function __construct(
        public readonly string $environment,
        public readonly ?string $release,
        public readonly string $runtime,
        public readonly string $runtimeVersion,
        public readonly string $framework,
        public readonly ?string $frameworkVersion,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'release' => $this->release,
            'runtime' => $this->runtime,
            'runtime_version' => $this->runtimeVersion,
            'framework' => $this->framework,
            'framework_version' => $this->frameworkVersion,
        ];
    }
}

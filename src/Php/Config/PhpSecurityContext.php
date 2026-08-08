<?php

namespace AppRadar\Agent\Php\Config;

final class PhpSecurityContext
{
    public function __construct(
        public readonly string $environment,
        public readonly bool $displayErrors,
        public readonly string $phpVersion,
        public readonly bool $onlyLocalStatus,
        public readonly bool $databaseConfigured,
        public readonly bool $databasePasswordEmpty,
        public readonly bool $redisConfigured,
        public readonly bool $redisPasswordEmpty,
        public readonly SecuritySettings $security,
    ) {
    }

    public function isLocal(): bool
    {
        return in_array(strtolower($this->environment), ['local', 'testing'], true);
    }

    public static function fromConfig(PhpAgentConfig $config): self
    {
        return new self(
            environment: $config->environment,
            displayErrors: filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN),
            phpVersion: PHP_VERSION,
            onlyLocalStatus: $config->onlyLocal,
            databaseConfigured: $config->database->isConfigured(),
            databasePasswordEmpty: ($config->database->password ?? '') === '',
            redisConfigured: $config->redis->isConfigured(),
            redisPasswordEmpty: ($config->redis->password ?? '') === '',
            security: $config->security,
        );
    }
}

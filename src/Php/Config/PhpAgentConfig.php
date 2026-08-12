<?php

namespace AppRadar\Agent\Php\Config;

use InvalidArgumentException;
use RuntimeException;

final class PhpAgentConfig
{
    public function __construct(
        public readonly string $name,
        public readonly string $environment,
        public readonly bool $onlyLocal,
        public readonly string $routePath,
        public readonly string $statusToken,
        public readonly DatabaseConnectionConfig $database,
        public readonly RedisConnectionConfig $redis,
        public readonly SecuritySettings $security,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("AppRadar config file not found at [{$path}]");
        }

        $payload = require $path;

        if (! is_array($payload)) {
            throw new InvalidArgumentException('AppRadar config file must return an array');
        }

        return self::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function fromPayload(array $payload): self
    {
        $app = is_array($payload['app'] ?? null) ? $payload['app'] : [];
        $database = is_array($payload['database'] ?? null) ? $payload['database'] : [];
        $redis = is_array($payload['redis'] ?? null) ? $payload['redis'] : [];
        $route = is_array($payload['route'] ?? null) ? $payload['route'] : [];
        $security = is_array($payload['security'] ?? null) ? $payload['security'] : [];

        return new self(
            name: self::stringOr($app['name'] ?? null, 'unknown'),
            environment: self::stringOr($app['environment'] ?? null, 'production'),
            onlyLocal: (bool) ($payload['only_local'] ?? false),
            routePath: trim(self::stringOr($route['path'] ?? null, 'status'), '/'),
            statusToken: self::resolveSecret($payload),
            database: new DatabaseConnectionConfig(
                driver: self::nullableString($database['driver'] ?? null),
                host: self::nullableString($database['host'] ?? null),
                port: self::nullableInt($database['port'] ?? null),
                database: self::nullableString($database['database'] ?? null),
                username: self::nullableString($database['username'] ?? null),
                password: self::nullableString($database['password'] ?? null),
                dsn: self::nullableString($database['dsn'] ?? null),
            ),
            redis: new RedisConnectionConfig(
                host: self::nullableString($redis['host'] ?? null),
                port: self::nullableInt($redis['port'] ?? null) ?? 6379,
                password: self::nullableString($redis['password'] ?? null),
                database: self::nullableInt($redis['database'] ?? null) ?? 0,
                timeout: self::nullableFloat($redis['timeout'] ?? null) ?? 1.0,
            ),
            security: new SecuritySettings(
                publicUrl: self::nullableString($security['public_url'] ?? null),
                publicPath: self::nullableString($security['public_path'] ?? null),
                sslCheck: (bool) ($security['ssl_check'] ?? true),
                sslExpiryWarnDays: self::nullableInt($security['ssl_expiry_warn_days'] ?? null) ?? 14,
                sslTimeoutSeconds: self::nullableFloat($security['ssl_timeout_seconds'] ?? null) ?? 3.0,
                phpUnsupportedBelow: self::stringOr($security['php_unsupported_below'] ?? null, '8.2.0'),
                phpEolBelow: self::stringOr($security['php_eol_below'] ?? null, '8.1.0'),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveSecret(array $payload): string
    {
        foreach (['secret', 'status_token'] as $key) {
            $configured = $payload[$key] ?? null;
            if (is_string($configured) && trim($configured) !== '') {
                return trim($configured);
            }
        }

        foreach (['APPRADAR_SECRET', 'APPRADAR_STATUS_TOKEN'] as $envKey) {
            $fromEnv = getenv($envKey);
            if (is_string($fromEnv) && trim($fromEnv) !== '') {
                return trim($fromEnv);
            }
        }

        return '';
    }

    private static function stringOr(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

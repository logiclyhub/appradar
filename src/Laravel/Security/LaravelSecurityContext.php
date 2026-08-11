<?php

namespace AppRadar\Agent\Laravel\Security;

final class LaravelSecurityContext
{
    /**
     * @param  array<int, string>  $missingPhpExtensions
     * @param  array<int, string>  $unwritableStoragePaths
     */
    public function __construct(
        public readonly string $environment,
        public readonly bool $debug,
        public readonly ?string $appKey,
        public readonly bool $sessionSecure,
        public readonly bool $sessionHttpOnly,
        public readonly ?string $sessionSameSite,
        public readonly string $sessionDriver,
        public readonly bool $displayErrors,
        public readonly string $phpVersion,
        public readonly string $publicPath,
        public readonly string $storagePath,
        public readonly bool $onlyLocalStatus,
        public readonly bool $statusTokenConfigured,
        public readonly bool $databasePasswordEmpty,
        public readonly string $databaseDriver,
        public readonly bool $redisConfigured,
        public readonly bool $redisPasswordEmpty,
        public readonly string $queueDriver,
        public readonly string $cacheDriver,
        public readonly string $filesystemDisk,
        public readonly bool $publicStorageLinkPresent,
        public readonly bool $configCachePresent,
        public readonly array $missingPhpExtensions,
        public readonly array $unwritableStoragePaths,
        public readonly bool $telescopeEnabled,
        public readonly bool $composerAuditEnabled,
        public readonly ?string $publicUrl,
        public readonly bool $sslCheckEnabled,
        public readonly int $sslExpiryWarnDays,
        public readonly float $sslTimeoutSeconds,
        public readonly string $phpUnsupportedBelow,
        public readonly string $phpEolBelow,
        public readonly string $basePath,
    ) {
    }

    public function isLocal(): bool
    {
        return in_array(strtolower($this->environment), ['local', 'testing'], true);
    }

    public static function fromLaravel(): self
    {
        $defaultRedis = (string) config('database.redis.default.host', '');
        $redisPassword = config('database.redis.default.password');
        $databaseDriver = (string) config('database.connections.'.config('database.default').'.driver', 'mysql');
        $queueConnection = (string) config('queue.default', 'sync');
        $queueDriver = (string) config("queue.connections.{$queueConnection}.driver", $queueConnection);
        $cacheStore = (string) config('cache.default', 'file');
        $cacheDriver = (string) config("cache.stores.{$cacheStore}.driver", $cacheStore);
        $sessionDriver = (string) config('session.driver', 'file');
        $filesystemDisk = (string) config('filesystems.default', 'local');
        $storagePath = storage_path();
        $publicPath = public_path();

        return new self(
            environment: (string) app()->environment(),
            debug: (bool) config('app.debug'),
            appKey: is_string(config('app.key')) ? config('app.key') : null,
            sessionSecure: (bool) config('session.secure'),
            sessionHttpOnly: (bool) config('session.http_only', true),
            sessionSameSite: is_string(config('session.same_site')) ? config('session.same_site') : null,
            sessionDriver: $sessionDriver,
            displayErrors: filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN),
            phpVersion: PHP_VERSION,
            publicPath: $publicPath,
            storagePath: $storagePath,
            onlyLocalStatus: (bool) config('appradar.only_local', false),
            statusTokenConfigured: trim((string) config('appradar.status_token', '')) !== '',
            databasePasswordEmpty: trim((string) config('database.connections.'.config('database.default').'.password', '')) === '',
            databaseDriver: $databaseDriver,
            redisConfigured: $defaultRedis !== '',
            redisPasswordEmpty: $redisPassword === null || $redisPassword === '',
            queueDriver: $queueDriver,
            cacheDriver: $cacheDriver,
            filesystemDisk: $filesystemDisk,
            publicStorageLinkPresent: self::publicStorageLinkPresent($publicPath),
            configCachePresent: is_file(base_path('bootstrap/cache/config.php')),
            missingPhpExtensions: self::missingPhpExtensions($databaseDriver, $queueDriver, $cacheDriver, $sessionDriver),
            unwritableStoragePaths: self::unwritableStoragePaths($storagePath),
            telescopeEnabled: (bool) config('telescope.enabled', false),
            composerAuditEnabled: (bool) config('appradar.security.composer_audit', false),
            publicUrl: self::resolvePublicUrl(),
            sslCheckEnabled: (bool) config('appradar.security.ssl_check', true),
            sslExpiryWarnDays: (int) config('appradar.security.ssl_expiry_warn_days', 14),
            sslTimeoutSeconds: (float) config('appradar.security.ssl_timeout_seconds', 3.0),
            phpUnsupportedBelow: (string) config('appradar.security.php_unsupported_below', '8.2.0'),
            phpEolBelow: (string) config('appradar.security.php_eol_below', '8.1.0'),
            basePath: base_path(),
        );
    }

    private static function resolvePublicUrl(): ?string
    {
        $override = config('appradar.security.public_url');
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        $appUrl = config('app.url');

        return is_string($appUrl) && trim($appUrl) !== '' ? trim($appUrl) : null;
    }

    private static function publicStorageLinkPresent(string $publicPath): bool
    {
        $link = rtrim($publicPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'storage';

        return is_link($link) || is_dir($link);
    }

    /**
     * @return array<int, string>
     */
    private static function missingPhpExtensions(
        string $databaseDriver,
        string $queueDriver,
        string $cacheDriver,
        string $sessionDriver,
    ): array {
        $required = [
            'openssl',
            'pdo',
            'mbstring',
            'tokenizer',
            'xml',
            'ctype',
            'json',
            'fileinfo',
            'bcmath',
        ];

        $pdoDriver = match ($databaseDriver) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            'sqlsrv' => 'pdo_sqlsrv',
            default => null,
        };

        if ($pdoDriver !== null) {
            $required[] = $pdoDriver;
        }

        if (in_array('redis', [$queueDriver, $cacheDriver, $sessionDriver], true)) {
            $required[] = 'redis';
        }

        $missing = [];

        foreach (array_unique($required) as $extension) {
            if (! extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        return array_values($missing);
    }

    /**
     * @return array<int, string>
     */
    private static function unwritableStoragePaths(string $storagePath): array
    {
        $relative = [
            '',
            'logs',
            'framework',
            'framework/cache',
            'framework/sessions',
            'framework/views',
        ];

        $unwritable = [];

        foreach ($relative as $suffix) {
            $path = rtrim($storagePath, DIRECTORY_SEPARATOR)
                .($suffix !== '' ? DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $suffix) : '');

            if (! is_dir($path) || ! is_writable($path)) {
                $unwritable[] = $suffix === '' ? 'storage' : 'storage/'.$suffix;
            }
        }

        return $unwritable;
    }
}

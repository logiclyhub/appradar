<?php

namespace AppRadar\Agent\Php\Config;

use InvalidArgumentException;

final class DatabaseConnectionConfig
{
    public function __construct(
        public readonly ?string $driver,
        public readonly ?string $host,
        public readonly ?int $port,
        public readonly ?string $database,
        public readonly ?string $username,
        public readonly ?string $password,
        public readonly ?string $dsn,
    ) {
    }

    public function isConfigured(): bool
    {
        if (is_string($this->dsn) && trim($this->dsn) !== '') {
            return true;
        }

        return is_string($this->driver)
            && trim($this->driver) !== ''
            && is_string($this->database)
            && trim($this->database) !== '';
    }

    public function pdoDsn(): string
    {
        if (is_string($this->dsn) && trim($this->dsn) !== '') {
            return $this->dsn;
        }

        $driver = strtolower((string) $this->driver);

        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host ?? '127.0.0.1',
                $this->port ?? 3306,
                $this->database,
            ),
            'pgsql', 'postgresql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->host ?? '127.0.0.1',
                $this->port ?? 5432,
                $this->database,
            ),
            'sqlite' => sprintf('sqlite:%s', $this->database),
            default => throw new InvalidArgumentException("Unsupported database driver [{$driver}]"),
        };
    }

    public function driverName(): string
    {
        if (is_string($this->dsn) && str_contains($this->dsn, ':')) {
            return strtolower(strstr($this->dsn, ':', true) ?: 'unknown');
        }

        $driver = strtolower((string) $this->driver);

        return $driver === 'postgresql' ? 'pgsql' : $driver;
    }
}

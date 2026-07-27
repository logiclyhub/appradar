<?php

namespace AppRadar\Agent\Laravel\Checks;

use Illuminate\Database\DatabaseManager;
use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\DatabaseStatus;
use Throwable;

class DatabaseCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {
    }

    public function run(): DatabaseStatus
    {
        try {
            $connection = $this->database->connection();
            $startedAt = microtime(true);
            $connection->select('SELECT 1 AS alive');
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
            $driver = $connection->getDriverName();
            $version = $this->serverVersion($connection);

            return new DatabaseStatus(
                status: $responseTimeMs > 1000 ? StatusCodes::WARN : StatusCodes::OK,
                connected: true,
                connection: $connection->getName(),
                type: $this->databaseType($driver, $version),
                database: $connection->getDatabaseName(),
                sizeMb: $this->databaseSizeMb($driver, $connection->getDatabaseName()),
                responseTimeMs: $responseTimeMs,
            );
        } catch (Throwable $throwable) {
            return new DatabaseStatus(
                status: StatusCodes::ERROR,
                connected: false,
                connection: config('database.default'),
                type: null,
                database: (string) config('database.connections.'.config('database.default').'.database'),
                sizeMb: null,
                responseTimeMs: null,
                message: $throwable->getMessage(),
            );
        }
    }

    private function serverVersion(object $connection): ?string
    {
        try {
            $result = $connection->selectOne('SELECT VERSION() AS version');

            return is_object($result) && isset($result->version) ? (string) $result->version : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function databaseType(string $driver, ?string $version): string
    {
        if ($driver === 'mysql' && is_string($version) && str_contains(strtolower($version), 'mariadb')) {
            return 'mariadb';
        }

        return match ($driver) {
            'mysql' => 'mysql',
            'pgsql' => 'postgresql',
            'sqlsrv' => 'sqlserver',
            default => $driver,
        };
    }

    private function databaseSizeMb(string $driver, ?string $database): ?float
    {
        if ($database === null || $database === '') {
            return null;
        }

        try {
            $connection = $this->database->connection();

            return match ($driver) {
                'mysql' => $this->mysqlSizeMb($connection, $database),
                'pgsql' => $this->pgsqlSizeMb($connection),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function mysqlSizeMb(object $connection, string $database): ?float
    {
        $result = $connection->selectOne(
            'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables
             WHERE table_schema = ?',
            [$database],
        );

        if (!is_object($result) || !isset($result->size_mb)) {
            return null;
        }

        return $result->size_mb !== null ? (float) $result->size_mb : null;
    }

    private function pgsqlSizeMb(object $connection): ?float
    {
        $result = $connection->selectOne(
            'SELECT ROUND(pg_database_size(current_database())::numeric / 1024 / 1024, 2) AS size_mb',
        );

        if (!is_object($result) || !isset($result->size_mb)) {
            return null;
        }

        return $result->size_mb !== null ? (float) $result->size_mb : null;
    }
}

<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\DatabaseStatus;
use AppRadar\Agent\Php\Config\DatabaseConnectionConfig;
use PDO;
use Throwable;

class DatabaseCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly DatabaseConnectionConfig $connection,
    ) {
    }

    public function run(): DatabaseStatus
    {
        if (! $this->connection->isConfigured()) {
            return new DatabaseStatus(
                status: StatusCodes::WARN,
                connected: false,
                connection: 'default',
                type: null,
                database: $this->connection->database,
                sizeMb: null,
                responseTimeMs: null,
                message: 'Database credentials are not configured in appradar.php',
            );
        }

        try {
            $pdo = new PDO(
                $this->connection->pdoDsn(),
                $this->connection->username,
                $this->connection->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ],
            );

            $startedAt = microtime(true);
            $pdo->query('SELECT 1');
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
            $driver = $this->connection->driverName();
            $version = $this->serverVersion($pdo);

            return new DatabaseStatus(
                status: $responseTimeMs > 1000 ? StatusCodes::WARN : StatusCodes::OK,
                connected: true,
                connection: 'default',
                type: $this->databaseType($driver, $version),
                database: $this->connection->database,
                sizeMb: $this->databaseSizeMb($pdo, $driver),
                responseTimeMs: $responseTimeMs,
            );
        } catch (Throwable $throwable) {
            return new DatabaseStatus(
                status: StatusCodes::ERROR,
                connected: false,
                connection: 'default',
                type: $this->connection->driver,
                database: $this->connection->database,
                sizeMb: null,
                responseTimeMs: null,
                message: $throwable->getMessage(),
            );
        }
    }

    private function serverVersion(PDO $pdo): ?string
    {
        try {
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return is_string($version) ? $version : null;
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

    private function databaseSizeMb(PDO $pdo, string $driver): ?float
    {
        try {
            return match ($driver) {
                'mysql' => $this->mysqlSizeMb($pdo),
                'pgsql' => $this->pgsqlSizeMb($pdo),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function mysqlSizeMb(PDO $pdo): ?float
    {
        $database = $this->connection->database;

        if ($database === null || $database === '') {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables
             WHERE table_schema = ?',
        );
        $statement->execute([$database]);
        $size = $statement->fetchColumn();

        return is_numeric($size) ? (float) $size : null;
    }

    private function pgsqlSizeMb(PDO $pdo): ?float
    {
        $size = $pdo->query(
            'SELECT ROUND(pg_database_size(current_database())::numeric / 1024 / 1024, 2) AS size_mb',
        )->fetchColumn();

        return is_numeric($size) ? (float) $size : null;
    }
}

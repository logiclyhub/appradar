<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\RedisStatus;
use AppRadar\Agent\Php\Config\RedisConnectionConfig;
use Throwable;

class RedisCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly RedisConnectionConfig $connection,
    ) {
    }

    public function run(): RedisStatus
    {
        if (! $this->connection->isConfigured()) {
            return new RedisStatus(
                status: StatusCodes::WARN,
                connected: false,
                connection: 'default',
                client: null,
                database: $this->connection->database,
                keyCount: null,
                responseTimeMs: null,
                instanceMemoryUsedMb: null,
                instanceMemoryMaxMb: null,
                message: 'Redis credentials are not configured in appradar.php',
            );
        }

        if (extension_loaded('redis') && class_exists(\Redis::class)) {
            return $this->runWithPhpRedis();
        }

        if (class_exists(\Predis\Client::class)) {
            return $this->runWithPredis();
        }

        return new RedisStatus(
            status: StatusCodes::ERROR,
            connected: false,
            connection: 'default',
            client: null,
            database: $this->connection->database,
            keyCount: null,
            responseTimeMs: null,
            instanceMemoryUsedMb: null,
            instanceMemoryMaxMb: null,
            message: 'No Redis client available. Install ext-redis or predis/predis',
        );
    }

    private function runWithPhpRedis(): RedisStatus
    {
        try {
            $client = new \Redis();
            $startedAt = microtime(true);
            $connected = $client->connect(
                (string) $this->connection->host,
                $this->connection->port,
                $this->connection->timeout,
            );

            if ($connected !== true) {
                throw new \RuntimeException('Unable to connect to Redis');
            }

            if (is_string($this->connection->password) && $this->connection->password !== '') {
                $client->auth($this->connection->password);
            }

            $client->select($this->connection->database);
            $client->ping();
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
            $info = $this->normalizeInfo($client->info('memory'));
            $maxMemory = isset($info['maxmemory']) ? (int) $info['maxmemory'] : null;

            return new RedisStatus(
                status: $responseTimeMs > 100 ? StatusCodes::WARN : StatusCodes::OK,
                connected: true,
                connection: 'default',
                client: 'phpredis',
                database: $this->connection->database,
                keyCount: (int) $client->dbSize(),
                responseTimeMs: $responseTimeMs,
                instanceMemoryUsedMb: $this->bytesToMb($info['used_memory'] ?? null),
                instanceMemoryMaxMb: $maxMemory !== null && $maxMemory > 0 ? $this->bytesToMb($maxMemory) : null,
            );
        } catch (Throwable $throwable) {
            return $this->errorStatus('phpredis', $throwable->getMessage());
        }
    }

    private function runWithPredis(): RedisStatus
    {
        try {
            $parameters = [
                'host' => $this->connection->host,
                'port' => $this->connection->port,
                'database' => $this->connection->database,
                'timeout' => $this->connection->timeout,
            ];

            if (is_string($this->connection->password) && $this->connection->password !== '') {
                $parameters['password'] = $this->connection->password;
            }

            $client = new \Predis\Client($parameters);
            $startedAt = microtime(true);
            $client->ping();
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
            $info = $this->normalizeInfo($client->info('memory'));
            $maxMemory = isset($info['maxmemory']) ? (int) $info['maxmemory'] : null;

            return new RedisStatus(
                status: $responseTimeMs > 100 ? StatusCodes::WARN : StatusCodes::OK,
                connected: true,
                connection: 'default',
                client: 'predis',
                database: $this->connection->database,
                keyCount: (int) $client->dbsize(),
                responseTimeMs: $responseTimeMs,
                instanceMemoryUsedMb: $this->bytesToMb($info['used_memory'] ?? null),
                instanceMemoryMaxMb: $maxMemory !== null && $maxMemory > 0 ? $this->bytesToMb($maxMemory) : null,
            );
        } catch (Throwable $throwable) {
            return $this->errorStatus('predis', $throwable->getMessage());
        }
    }

    private function errorStatus(string $client, string $message): RedisStatus
    {
        return new RedisStatus(
            status: StatusCodes::ERROR,
            connected: false,
            connection: 'default',
            client: $client,
            database: $this->connection->database,
            keyCount: null,
            responseTimeMs: null,
            instanceMemoryUsedMb: null,
            instanceMemoryMaxMb: null,
            message: $message,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInfo(mixed $info): array
    {
        if (! is_array($info)) {
            return [];
        }

        if (isset($info['Memory']) && is_array($info['Memory'])) {
            return $info['Memory'];
        }

        return $info;
    }

    private function bytesToMb(mixed $bytes): ?float
    {
        if (! is_numeric($bytes)) {
            return null;
        }

        return round(((float) $bytes) / 1024 / 1024, 2);
    }
}

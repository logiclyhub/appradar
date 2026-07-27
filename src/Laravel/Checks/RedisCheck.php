<?php

namespace AppRadar\Agent\Laravel\Checks;

use Illuminate\Support\Facades\Redis;
use AppRadar\Agent\Core\CheckType;
use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Core\ValueObjects\CheckResult;
use AppRadar\Agent\Laravel\ValueObjects\RedisMeta;
use Throwable;

class RedisCheck implements StatusCheckInterface
{
    public function run(): CheckResult
    {
        $connectionName = 'default';

        try {
            $connection = Redis::connection($connectionName);
            $startedAt = microtime(true);
            $connection->ping();
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
            $info = $this->normalizeInfo($connection->info('memory'));
            $maxMemory = isset($info['maxmemory']) ? (int) $info['maxmemory'] : null;

            return new CheckResult(
                type: CheckType::Redis,
                status: $responseTimeMs > 100 ? StatusCodes::WARN : StatusCodes::OK,
                meta: new RedisMeta(
                    connected: true,
                    connection: $connectionName,
                    client: (string) config('database.redis.client'),
                    database: (int) config("database.redis.{$connectionName}.database", 0),
                    keyCount: (int) $connection->dbsize(),
                    responseTimeMs: $responseTimeMs,
                    instanceMemoryUsedMb: $this->bytesToMb($info['used_memory'] ?? null),
                    instanceMemoryMaxMb: $maxMemory !== null && $maxMemory > 0 ? $this->bytesToMb($maxMemory) : null,
                ),
            );
        } catch (Throwable $throwable) {
            return new CheckResult(
                type: CheckType::Redis,
                status: StatusCodes::ERROR,
                meta: new RedisMeta(
                    connected: false,
                    connection: $connectionName,
                    client: (string) config('database.redis.client'),
                    database: (int) config("database.redis.{$connectionName}.database", 0),
                    keyCount: null,
                    responseTimeMs: null,
                    instanceMemoryUsedMb: null,
                    instanceMemoryMaxMb: null,
                    message: $throwable->getMessage(),
                ),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInfo(mixed $info): array
    {
        if (!is_array($info)) {
            return [];
        }

        if (isset($info['Memory']) && is_array($info['Memory'])) {
            return $info['Memory'];
        }

        return $info;
    }

    private function bytesToMb(mixed $bytes): ?float
    {
        if (!is_numeric($bytes)) {
            return null;
        }

        return round(((float) $bytes) / 1024 / 1024, 2);
    }
}

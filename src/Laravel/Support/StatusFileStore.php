<?php

namespace AppRadar\Agent\Laravel\Support;

use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

class StatusFileStore
{
    public function __construct(
        private ?string $relativePath = null,
    ) {
    }

    public function path(string $filename): string
    {
        $relativePath = $this->relativePath ?? (string) config('appradar.storage_path', 'app/status');

        return storage_path(trim($relativePath, '/').'/'.$filename);
    }

    public function writeJson(string $filename, array $payload): void
    {
        $path = $this->path($filename);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), true);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $updater
     * @return array<string, mixed>
     */
    public function updateJson(string $filename, callable $updater): array
    {
        $path = $this->path($filename);
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open status file [{$path}].");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to lock status file [{$path}].");
            }

            $contents = stream_get_contents($handle);
            $payload = [];

            if (is_string($contents) && trim($contents) !== '') {
                try {
                    $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
                    $payload = is_array($decoded) ? $decoded : [];
                } catch (JsonException) {
                    $payload = [];
                }
            }

            $updated = $updater($payload);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $updated;
        } finally {
            fclose($handle);
        }
    }

    public function exists(string $filename): bool
    {
        return is_file($this->path($filename));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readJson(string $filename): ?array
    {
        $path = $this->path($filename);

        if (!is_file($path)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}

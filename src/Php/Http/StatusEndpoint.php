<?php

namespace AppRadar\Agent\Php\Http;

use AppRadar\Agent\Php\Config\PhpAgentConfig;
use AppRadar\Agent\Php\PhpAdapter;

final class StatusEndpoint
{
    public function __construct(
        private readonly PhpAgentConfig $config,
        private readonly PhpAdapter $adapter,
    ) {
    }

    public static function fromConfigFile(string $path): self
    {
        $config = PhpAgentConfig::fromFile($path);

        return new self($config, new PhpAdapter($config));
    }

    public function respond(): void
    {
        if ($this->config->onlyLocal && ! $this->isLocalRequest()) {
            $this->sendJson(['message' => 'Not found'], 404);

            return;
        }

        if (! $this->tokenAllowsRequest()) {
            $this->sendJson(['message' => 'Unauthorized'], 401);

            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'GET') {
            $this->sendJson($this->adapter->statusPayload());

            return;
        }

        if ($method === 'POST') {
            $timeout = isset($_GET['timeout']) && is_numeric($_GET['timeout'])
                ? (int) $_GET['timeout']
                : 600;

            $this->sendJson($this->adapter->runTests($timeout));

            return;
        }

        $this->sendJson(['message' => 'Method not allowed'], 405);
    }

    private function tokenAllowsRequest(): bool
    {
        $expected = trim($this->config->statusToken);

        if ($expected === '') {
            return true;
        }

        $provided = $this->bearerToken() ?? $this->headerValue('X-AppRadar-Token');

        return is_string($provided) && $provided !== '' && hash_equals($expected, $provided);
    }

    private function bearerToken(): ?string
    {
        $header = $this->headerValue('Authorization');

        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    private function headerValue(string $name): ?string
    {
        $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function isLocalRequest(): bool
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        return in_array($remote, ['127.0.0.1', '::1'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

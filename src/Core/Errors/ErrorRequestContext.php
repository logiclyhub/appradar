<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorRequestContext
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly array $context,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $this->headers,
            'context' => $this->context,
        ];
    }
}

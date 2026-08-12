<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorScrubber
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param  array<int, string>  $sensitiveKeyFragments
     */
    public function __construct(
        private readonly int $maxMessageLength = 4000,
        private readonly array $sensitiveKeyFragments = [
            'password',
            'passwd',
            'secret',
            'token',
            'authorization',
            'cookie',
            'api_key',
            'apikey',
            'access_key',
            'private_key',
            'credit_card',
            'card_number',
        ],
    ) {
    }

    public function scrubMessage(string $message): string
    {
        if (strlen($message) <= $this->maxMessageLength) {
            return $message;
        }

        return substr($message, 0, $this->maxMessageLength).'…';
    }

    public function scrubRequest(ErrorRequestContext $request): ErrorRequestContext
    {
        return new ErrorRequestContext(
            method: $request->method,
            url: $this->scrubUrl($request->url),
            headers: $this->scrubAssociative($request->headers),
            context: $this->scrubAssociative($request->context),
        );
    }

    private function scrubUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['query']) || ! is_string($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        $scrubbedQuery = $this->scrubAssociative($query);

        $rebuilt = '';
        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'].'://';
        }
        if (isset($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }
        if (isset($parts['path'])) {
            $rebuilt .= $parts['path'];
        }

        $queryString = http_build_query($scrubbedQuery);
        if ($queryString !== '') {
            $rebuilt .= '?'.$queryString;
        }

        return $rebuilt;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function scrubAssociative(array $values): array
    {
        $scrubbed = [];

        foreach ($values as $key => $value) {
            $keyString = (string) $key;

            if ($this->isSensitiveKey($keyString)) {
                $scrubbed[$keyString] = self::REDACTED;
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $scrubbed[$keyString] = $this->scrubAssociative($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $scrubbed[$keyString] = $value;
                continue;
            }

            $scrubbed[$keyString] = self::REDACTED;
        }

        return $scrubbed;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if ($normalized === '_token') {
            return true;
        }

        foreach ($this->sensitiveKeyFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}

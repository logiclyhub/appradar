<?php

namespace AppRadar\Agent\Core\Security;

use Throwable;

final class HttpFetcher implements HttpFetcherInterface
{
    public function get(string $url, float $timeoutSeconds = 3.0): HttpFetchResult
    {
        $url = trim($url);

        if ($url === '') {
            return new HttpFetchResult(
                reached: false,
                statusCode: 0,
                body: '',
                message: 'Empty URL',
            );
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeoutSeconds,
                    'ignore_errors' => true,
                    'header' => "User-Agent: AppRadar-Agent\r\nAccept: */*\r\n",
                    'follow_location' => 1,
                    'max_redirects' => 3,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);

            if ($body === false) {
                return new HttpFetchResult(
                    reached: false,
                    statusCode: 0,
                    body: '',
                    message: 'Request failed',
                );
            }

            $statusCode = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches) === 1) {
                        $statusCode = (int) $matches[1];
                    }
                }
            }

            return new HttpFetchResult(
                reached: true,
                statusCode: $statusCode,
                body: is_string($body) ? substr($body, 0, 4096) : '',
            );
        } catch (Throwable $throwable) {
            return new HttpFetchResult(
                reached: false,
                statusCode: 0,
                body: '',
                message: $throwable->getMessage(),
            );
        }
    }
}

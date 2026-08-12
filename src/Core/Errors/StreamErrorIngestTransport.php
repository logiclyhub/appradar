<?php

namespace AppRadar\Agent\Core\Errors;

use Throwable;

final class StreamErrorIngestTransport implements ErrorIngestTransportInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    public function post(string $url, string $body, array $headers, float $timeoutSeconds): ErrorIngestTransportResult
    {
        try {
            $headerLines = '';
            foreach ($headers as $name => $value) {
                $headerLines .= $name.': '.$value."\r\n";
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headerLines,
                    'content' => $body,
                    'timeout' => $timeoutSeconds,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            $statusCode = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches) === 1) {
                        $statusCode = (int) $matches[1];
                    }
                }
            }

            if ($response === false && $statusCode === 0) {
                return new ErrorIngestTransportResult(ok: false, statusCode: 0, message: 'Request failed');
            }

            $ok = $statusCode >= 200 && $statusCode < 300;

            return new ErrorIngestTransportResult(
                ok: $ok,
                statusCode: $statusCode,
                message: $ok ? null : 'HTTP '.$statusCode,
            );
        } catch (Throwable $throwable) {
            return new ErrorIngestTransportResult(
                ok: false,
                statusCode: 0,
                message: $throwable->getMessage(),
            );
        }
    }
}

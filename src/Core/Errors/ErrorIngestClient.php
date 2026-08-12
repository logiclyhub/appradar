<?php

namespace AppRadar\Agent\Core\Errors;

use Throwable;

final class ErrorIngestClient
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $token,
        private readonly float $timeoutSeconds,
        private readonly ErrorIngestTransportInterface $transport,
    ) {
    }

    public function send(ErrorEvent $event): ErrorIngestResult
    {
        try {
            $body = json_encode($event->toArray(), JSON_THROW_ON_ERROR);

            $result = $this->transport->post(
                $this->endpoint,
                $body,
                [
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'AppRadar-Agent',
                ],
                $this->timeoutSeconds,
            );

            return new ErrorIngestResult(
                ok: $result->ok,
                message: $result->message,
                statusCode: $result->statusCode,
            );
        } catch (Throwable $throwable) {
            return new ErrorIngestResult(
                ok: false,
                message: $throwable->getMessage(),
                statusCode: 0,
            );
        }
    }
}

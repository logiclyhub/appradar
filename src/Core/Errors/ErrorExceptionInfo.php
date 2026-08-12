<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorExceptionInfo
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly Stacktrace $stacktrace,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'stacktrace' => $this->stacktrace->toArray(),
        ];
    }
}

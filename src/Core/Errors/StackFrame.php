<?php

namespace AppRadar\Agent\Core\Errors;

final class StackFrame
{
    public function __construct(
        public readonly string $filename,
        public readonly string $absPath,
        public readonly int $lineno,
        public readonly string $function,
        public readonly bool $inApp,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'abs_path' => $this->absPath,
            'lineno' => $this->lineno,
            'function' => $this->function,
            'in_app' => $this->inApp,
        ];
    }
}

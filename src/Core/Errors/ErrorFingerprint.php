<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorFingerprint
{
    /**
     * @param  array<int, string>  $parts
     */
    public function __construct(
        private readonly array $parts,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function parts(): array
    {
        return $this->parts;
    }

    /**
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return $this->parts;
    }
}

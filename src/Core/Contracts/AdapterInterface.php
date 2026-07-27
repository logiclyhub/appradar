<?php

namespace AppRadar\Agent\Core\Contracts;

interface AdapterInterface
{
    /**
     * @return array<string, mixed>
     */
    public function statusPayload(): array;

    /**
     * @return array<string, mixed>
     */
    public function runTests(int $timeout = 600): array;
}

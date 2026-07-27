<?php

namespace AppRadar\Agent\Core\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Laravel\ValueObjects\TestsMeta;

class TestRunResponse implements Arrayable, JsonSerializable
{
    public function __construct(
        private readonly int $status,
        private readonly TestsMeta $result,
    ) {
    }

    /**
     * @return array{status:int,result:array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'result' => $this->result->toArray(),
        ];
    }

    /**
     * @return array{status:int,result:array<string,mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

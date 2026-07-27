<?php

namespace AppRadar\Agent\Core\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Core\CheckType;

class CheckResult implements Arrayable, JsonSerializable
{
    public function __construct(
        private readonly CheckType $type,
        private readonly int $status,
        private readonly Arrayable $meta,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array{key:string,label:string,status:int,meta:array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->type->value,
            'label' => $this->type->label(),
            'status' => $this->status,
            'meta' => $this->meta->toArray(),
        ];
    }

    /**
     * @return array{key:string,label:string,status:int,meta:array<string,mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

<?php

namespace AppRadar\Agent\Core\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

interface StatusSectionInterface extends Arrayable, JsonSerializable
{
    public function status(): int;

    public static function key(): string;

    public static function label(): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static;
}

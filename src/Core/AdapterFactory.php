<?php

namespace AppRadar\Agent\Core;

use Illuminate\Contracts\Container\Container;
use RuntimeException;
use AppRadar\Agent\Core\Contracts\AdapterInterface;
use AppRadar\Agent\Laravel\LaravelAdapter;

class AdapterFactory
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function make(string $projectType): AdapterInterface
    {
        return match ($projectType) {
            'laravel' => $this->container->make(LaravelAdapter::class),
            default => throw new RuntimeException('Unsupported framework'),
        };
    }
}

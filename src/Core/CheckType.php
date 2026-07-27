<?php

namespace AppRadar\Agent\Core;

enum CheckType: string
{
    case Database = 'database';
    case Redis = 'redis';
    case Scheduler = 'scheduler';
    case Queue = 'queue';
    case Tests = 'tests';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Database',
            self::Redis => 'Redis',
            self::Scheduler => 'Scheduler',
            self::Queue => 'Queue',
            self::Tests => 'Tests',
        };
    }
}

<?php

namespace AppRadar\Agent\Core;

use Illuminate\Foundation\Application;
use RuntimeException;

class ProjectTypeDetector
{
    public function detect(): string
    {
        $matches = array_filter([
            'laravel' => $this->isLaravel(),
        ]);

        if ($matches === []) {
            throw new RuntimeException('Unsupported framework');
        }

        if (count($matches) > 1) {
            throw new RuntimeException('Ambiguous project type');
        }

        return array_key_first($matches);
    }

    private function isLaravel(): bool
    {
        return class_exists(Application::class)
            && function_exists('app')
            && app() instanceof Application
            && function_exists('base_path')
            && is_file(base_path('bootstrap/app.php'));
    }
}

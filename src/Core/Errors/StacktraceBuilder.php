<?php

namespace AppRadar\Agent\Core\Errors;

use Throwable;

final class StacktraceBuilder
{
    public function __construct(
        private readonly string $basePath,
        private readonly int $maxFrames = 50,
    ) {
    }

    public function fromThrowable(Throwable $throwable): Stacktrace
    {
        $trace = $throwable->getTrace();

        array_unshift($trace, [
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'function' => '{main}',
        ]);

        return $this->fromTrace($trace);
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     */
    public function fromTrace(array $trace): Stacktrace
    {
        $basePath = rtrim(str_replace('\\', '/', $this->basePath), '/');
        $frames = [];

        foreach ($trace as $entry) {
            if (count($frames) >= $this->maxFrames) {
                break;
            }

            if (! is_array($entry)) {
                continue;
            }

            $absPath = isset($entry['file']) && is_string($entry['file'])
                ? str_replace('\\', '/', $entry['file'])
                : '[internal]';

            $filename = $this->relativePath($absPath, $basePath);
            $function = $this->formatFunction($entry);
            $inApp = $this->isInApp($filename);

            $frames[] = new StackFrame(
                filename: $filename,
                absPath: $absPath,
                lineno: isset($entry['line']) && is_numeric($entry['line']) ? (int) $entry['line'] : 0,
                function: $function,
                inApp: $inApp,
            );
        }

        return new Stacktrace($frames);
    }

    private function relativePath(string $absPath, string $basePath): string
    {
        if ($basePath !== '' && str_starts_with($absPath, $basePath.'/')) {
            return substr($absPath, strlen($basePath) + 1);
        }

        return $absPath;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function formatFunction(array $entry): string
    {
        $function = isset($entry['function']) && is_string($entry['function'])
            ? $entry['function']
            : '{unknown}';

        if (isset($entry['class']) && is_string($entry['class'])) {
            $type = isset($entry['type']) && is_string($entry['type']) ? $entry['type'] : '::';

            return $entry['class'].$type.$function;
        }

        return $function;
    }

    private function isInApp(string $filename): bool
    {
        if ($filename === '[internal]') {
            return false;
        }

        $normalized = str_replace('\\', '/', $filename);

        if (str_contains($normalized, '/vendor/') || str_starts_with($normalized, 'vendor/')) {
            return false;
        }

        return true;
    }
}

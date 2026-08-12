<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorFingerprintBuilder
{
    public function build(string $exceptionType, Stacktrace $stacktrace): ErrorFingerprint
    {
        $frame = $stacktrace->topInAppFrame() ?? $stacktrace->firstFrame();

        if ($frame === null) {
            return new ErrorFingerprint([$exceptionType, 'unknown']);
        }

        return new ErrorFingerprint([
            $exceptionType,
            $frame->function,
            $frame->filename.':'.$frame->lineno,
        ]);
    }
}

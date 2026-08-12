<?php

namespace AppRadar\Agent\Core\Errors;

use Throwable;

final class ErrorCapturePolicy
{
    public function __construct(
        private readonly ErrorIgnoreList $ignoreList,
        private readonly float $sampleRate,
    ) {
    }

    public function shouldCapture(Throwable $throwable): ErrorCaptureDecision
    {
        if ($this->ignoreList->matches($throwable)) {
            return new ErrorCaptureDecision(false, 'ignored');
        }

        if ($this->sampleRate <= 0.0) {
            return new ErrorCaptureDecision(false, 'sample_rate');
        }

        if ($this->sampleRate < 1.0) {
            $roll = random_int(1, 10_000) / 10_000;

            if ($roll > $this->sampleRate) {
                return new ErrorCaptureDecision(false, 'sampled_out');
            }
        }

        return new ErrorCaptureDecision(true);
    }
}

<?php

namespace AppRadar\Agent\Core\Errors;

final class Stacktrace
{
    /**
     * @param  array<int, StackFrame>  $frames
     */
    public function __construct(
        private readonly array $frames,
    ) {
    }

    /**
     * @return array<int, StackFrame>
     */
    public function frames(): array
    {
        return $this->frames;
    }

    public function topInAppFrame(): ?StackFrame
    {
        foreach ($this->frames as $frame) {
            if ($frame->inApp) {
                return $frame;
            }
        }

        return null;
    }

    public function firstFrame(): ?StackFrame
    {
        return $this->frames[0] ?? null;
    }

    /**
     * @return array{frames: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'frames' => array_map(
                static fn (StackFrame $frame): array => $frame->toArray(),
                $this->frames,
            ),
        ];
    }
}

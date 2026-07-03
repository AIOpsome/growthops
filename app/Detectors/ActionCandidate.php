<?php

namespace App\Detectors;

final class ActionCandidate
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $type,
        public array $evidence,
        public float $confidence,
        public string $risk,
        public float $expectedUpside,
    ) {}
}

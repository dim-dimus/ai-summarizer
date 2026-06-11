<?php

namespace App\Services\Llm;

/**
 * Everything the job needs to persist after a successful summarization.
 */
class SummaryGeneration
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $resultText,
        public readonly string $model,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly ?float $costUsd,
        public readonly array $metadata = [],
    ) {}
}

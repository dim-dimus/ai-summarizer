<?php

namespace App\Services\Llm;

/**
 * Normalized result returned by every LlmClient adapter. Token fields are
 * nullable because not every provider reports usage; cost is computed only
 * when usage + a known rate exist.
 */
class LlmResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {}
}

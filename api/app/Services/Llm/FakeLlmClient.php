<?php

namespace App\Services\Llm;

/**
 * Deterministic test/dev adapter. Returns a canned summary derived from the
 * prompt so the full async pipeline can be exercised without an API key or
 * spending credits. Bound as the LlmClient in the test environment.
 */
class FakeLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $model = 'fake-model',
    ) {}

    public function summarize(string $system, string $prompt, int $maxTokens): LlmResult
    {
        $text = '[summary] '.\Illuminate\Support\Str::limit(trim($prompt), 200);

        return new LlmResult(
            text: $text,
            model: $this->model,
            inputTokens: (int) ceil(mb_strlen($prompt) / 4),
            outputTokens: (int) ceil(mb_strlen($text) / 4),
        );
    }
}

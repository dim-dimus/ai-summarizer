<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Messages API adapter. Calls POST /v1/messages over raw HTTP via
 * Laravel's Http client (no vendor SDK, per architecture rules) and maps the
 * response to the shared LlmResult.
 *
 * Wire format: x-api-key + anthropic-version headers; body { model, max_tokens,
 * system, messages: [{role:"user", content}] }; token usage at
 * response.usage.{input_tokens, output_tokens}.
 */
class AnthropicAdapter implements LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly string $version = '2023-06-01',
    ) {}

    public function summarize(string $system, string $prompt, int $maxTokens): LlmResult
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->retry(2, 1000, throw: false) // bounded retry for transient 429/5xx
            ->post("{$this->baseUrl}/v1/messages", [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Anthropic API error (HTTP {$response->status()}): ".$response->body()
            );
        }

        $data = $response->json();

        // content is an array of blocks; concatenate the text blocks.
        $text = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if ($text === '') {
            throw new RuntimeException('Anthropic API returned no text content.');
        }

        return new LlmResult(
            text: trim($text),
            model: $data['model'] ?? $this->model,
            inputTokens: $data['usage']['input_tokens'] ?? null,
            outputTokens: $data['usage']['output_tokens'] ?? null,
        );
    }
}

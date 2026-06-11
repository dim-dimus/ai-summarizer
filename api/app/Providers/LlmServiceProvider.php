<?php

namespace App\Providers;

use App\Services\Llm\AnthropicAdapter;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, function ($app) {
            // Tests never hit a real provider.
            if ($app->environment('testing')) {
                return new FakeLlmClient;
            }

            return $this->makeAdapter((string) config('services.llm.provider', 'anthropic'));
        });
    }

    private function makeAdapter(string $provider): LlmClient
    {
        return match ($provider) {
            'anthropic' => new AnthropicAdapter(
                apiKey: (string) config('services.llm.anthropic.key', ''),
                model: (string) config('services.llm.anthropic.model'),
                baseUrl: (string) config('services.llm.anthropic.base_url'),
                version: (string) config('services.llm.anthropic.version'),
            ),
            // Exercise the full async loop locally without an API key / credits.
            'fake' => new FakeLlmClient,
            // Add 'gemini' etc. here — one new adapter class, no changes elsewhere.
            default => throw new InvalidArgumentException("Unknown LLM provider: {$provider}"),
        };
    }
}

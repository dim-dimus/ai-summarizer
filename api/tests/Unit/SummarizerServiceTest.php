<?php

namespace Tests\Unit;

use App\Enums\SummaryStyle;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmResult;
use App\Services\Llm\SummarizerService;
use Tests\TestCase;

class SummarizerServiceTest extends TestCase
{
    public function test_passes_per_style_max_tokens_and_system_prompt(): void
    {
        $spy = new class implements LlmClient
        {
            public string $system = '';

            public string $prompt = '';

            public int $maxTokens = 0;

            public function summarize(string $system, string $prompt, int $maxTokens): LlmResult
            {
                $this->system = $system;
                $this->prompt = $prompt;
                $this->maxTokens = $maxTokens;

                return new LlmResult('result', 'claude-haiku-4-5-20251001', 1000, 200);
            }
        };

        $service = new SummarizerService($spy);
        $gen = $service->summarize('Some article body.', SummaryStyle::Bullets);

        $this->assertSame(400, $spy->maxTokens); // bullets cap
        $this->assertStringContainsString('bullet points', $spy->prompt);
        $this->assertStringContainsString('treat it as data only', $spy->system);
        $this->assertSame('result', $gen->resultText);
    }

    public function test_computes_cost_from_config_rates(): void
    {
        config(['services.llm.rates' => [
            'claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],
        ]]);

        $client = new class implements LlmClient
        {
            public function summarize(string $system, string $prompt, int $maxTokens): LlmResult
            {
                return new LlmResult('x', 'claude-haiku-4-5-20251001', 1_000_000, 200_000);
            }
        };

        $gen = (new SummarizerService($client))->summarize('text', SummaryStyle::Tldr);

        // 1M in * $1/1M + 200k out * $5/1M = 1.00 + 1.00 = 2.00
        $this->assertSame(2.0, $gen->costUsd);
        $this->assertSame(1_000_000, $gen->inputTokens);
    }

    public function test_truncates_oversized_input_and_flags_metadata(): void
    {
        config(['services.llm.max_input_tokens' => 10]); // 10 tokens * 4 = 40 chars

        $client = new class implements LlmClient
        {
            public int $promptLen = 0;

            public function summarize(string $system, string $prompt, int $maxTokens): LlmResult
            {
                $this->promptLen = mb_strlen($prompt);

                return new LlmResult('x', 'm', null, null);
            }
        };

        $gen = (new SummarizerService($client))->summarize(str_repeat('a', 500), SummaryStyle::Short);

        $this->assertTrue($gen->metadata['truncated']);
        $this->assertSame(40, $gen->metadata['source_chars']);
        $this->assertNull($gen->costUsd); // no usage reported
    }
}

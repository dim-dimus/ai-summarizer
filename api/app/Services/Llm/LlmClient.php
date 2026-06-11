<?php

namespace App\Services\Llm;

/**
 * Provider-agnostic summarization client. Adapters translate this shared
 * request to their vendor's wire format and map the response back to an
 * LlmResult (text + token usage). Prompt building and style logic live in
 * SummarizerService — adapters only do transport.
 */
interface LlmClient
{
    public function summarize(string $system, string $prompt, int $maxTokens): LlmResult;
}

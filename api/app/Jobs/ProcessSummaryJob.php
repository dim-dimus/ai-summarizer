<?php

namespace App\Jobs;

use App\Enums\SourceType;
use App\Enums\SummaryStatus;
use App\Models\Summary;
use App\Services\Content\ContentExtractor;
use App\Services\Content\SsrfException;
use App\Services\Llm\SummarizerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The async pipeline: pending → processing → completed | failed.
 * Extracts content (for URLs), summarizes via the provider-agnostic service,
 * and persists the result + token usage + cost. On final failure the row is
 * marked `failed` (and, in prod, SQS routes the message to the DLQ).
 */
class ProcessSummaryJob implements ShouldQueue
{
    use Queueable;

    /** Bounded retries before the message lands in the DLQ. */
    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $summaryId,
    ) {}

    public function handle(ContentExtractor $extractor, SummarizerService $summarizer): void
    {
        $summary = Summary::find($this->summaryId);
        if ($summary === null || $summary->status === SummaryStatus::Completed) {
            return; // deleted or already done — nothing to do
        }

        $summary->update(['status' => SummaryStatus::Processing]);

        // 1. Get the source text (extract for URLs, use pasted text otherwise).
        $metadata = $summary->metadata ?? [];
        if ($summary->source_type === SourceType::Url) {
            try {
                $extracted = $extractor->extract($summary->source_url);
            } catch (SsrfException $e) {
                // Deterministic block — no point retrying. Fail immediately.
                $this->fail($e);

                return;
            }
            $content = $extracted->text;
            if ($content === '') {
                throw new \RuntimeException('Extracted content was empty.');
            }
            $summary->title ??= $extracted->title;
            $metadata['source_domain'] = parse_url($extracted->finalUrl, PHP_URL_HOST);
        } else {
            $content = (string) $summary->original_text;
            $summary->title ??= \Illuminate\Support\Str::limit($content, 60);
        }

        // 2. Summarize (prompt + style + token budget + cost all in the service).
        $gen = $summarizer->summarize($content, $summary->style);

        // 3. Persist.
        $summary->update([
            'status' => SummaryStatus::Completed,
            'result_text' => $gen->resultText,
            'model' => $gen->model,
            'input_tokens' => $gen->inputTokens,
            'output_tokens' => $gen->outputTokens,
            'cost_usd' => $gen->costUsd,
            'metadata' => array_merge($metadata, $gen->metadata),
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Final failure (after all retries) → mark the row failed with a message.
     */
    public function failed(Throwable $e): void
    {
        Summary::where('id', $this->summaryId)->update([
            'status' => SummaryStatus::Failed->value,
            'error_message' => \Illuminate\Support\Str::limit($e->getMessage(), 1000),
        ]);
    }
}

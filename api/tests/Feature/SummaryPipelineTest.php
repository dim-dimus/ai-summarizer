<?php

namespace Tests\Feature;

use App\Enums\SummaryStatus;
use App\Jobs\ProcessSummaryJob;
use App\Models\Summary;
use App\Models\User;
use App\Services\Content\ContentExtractor;
use App\Services\Llm\SummarizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SummaryPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_summary_dispatches_the_job(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/summaries', [
            'source_type' => 'text',
            'text' => 'Body to summarize.',
            'style' => 'tldr',
        ])->assertStatus(202);

        Queue::assertPushed(ProcessSummaryJob::class);
    }

    public function test_job_completes_a_text_summary_with_tokens_and_cost(): void
    {
        config(['services.llm.rates' => [
            'fake-model' => ['input' => 1.00, 'output' => 5.00],
        ]]);

        $summary = Summary::factory()->create([
            'source_type' => 'text',
            'original_text' => 'A reasonably long piece of text that should be summarized by the fake client.',
            'style' => 'tldr',
            'status' => SummaryStatus::Pending,
        ]);

        (new ProcessSummaryJob($summary->id))->handle(
            app(ContentExtractor::class),
            app(SummarizerService::class),
        );

        $summary->refresh();
        $this->assertSame(SummaryStatus::Completed, $summary->status);
        $this->assertStringContainsString('[summary]', $summary->result_text);
        $this->assertSame('fake-model', $summary->model);
        $this->assertNotNull($summary->input_tokens);
        $this->assertNotNull($summary->output_tokens);
        $this->assertNotNull($summary->cost_usd);
        $this->assertNotNull($summary->completed_at);
    }

    public function test_ssrf_blocked_url_marks_summary_failed(): void
    {
        $summary = Summary::factory()->url()->create([
            'source_url' => 'http://169.254.169.254/latest/meta-data/',
            'style' => 'short',
            'status' => SummaryStatus::Pending,
        ]);

        try {
            ProcessSummaryJob::dispatchSync($summary->id);
        } catch (\Throwable) {
            // Sync queue re-throws after invoking failed(); that's expected.
        }

        $summary->refresh();
        $this->assertSame(SummaryStatus::Failed, $summary->status);
        $this->assertNotNull($summary->error_message);
    }
}

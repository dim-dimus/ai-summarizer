<?php

namespace Tests\Feature;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SummaryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // CRUD concerns only the HTTP contract — keep the worker pipeline out
        // (and prevent the sync queue from making real network calls).
        Queue::fake();
    }

    public function test_create_text_summary_returns_202_pending(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/summaries', [
            'source_type' => 'text',
            'text' => 'Some long piece of text to summarize.',
            'style' => 'tldr',
        ])->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.source_type', 'text')
            ->assertJsonPath('data.style', 'tldr');

        $this->assertDatabaseHas('summaries', ['status' => 'pending', 'source_type' => 'text']);
    }

    public function test_create_url_summary_returns_202(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/summaries', [
            'source_type' => 'url',
            'url' => 'https://example.com/article',
            'style' => 'bullets',
        ])->assertStatus(202)
            ->assertJsonPath('data.source_url', 'https://example.com/article');
    }

    public function test_create_rejects_url_xor_text_violations(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // text source_type but url supplied -> url prohibited, text missing
        $this->postJson('/api/summaries', [
            'source_type' => 'text',
            'url' => 'https://example.com',
            'style' => 'tldr',
        ])->assertStatus(422)->assertJsonValidationErrors(['url', 'text']);

        // bad style
        $this->postJson('/api/summaries', [
            'source_type' => 'text',
            'text' => 'hi',
            'style' => 'essay',
        ])->assertStatus(422)->assertJsonValidationErrors(['style']);

        // non-http url
        $this->postJson('/api/summaries', [
            'source_type' => 'url',
            'url' => 'ftp://example.com',
            'style' => 'tldr',
        ])->assertStatus(422)->assertJsonValidationErrors(['url']);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/summaries', [
            'source_type' => 'text', 'text' => 'hi', 'style' => 'tldr',
        ])->assertStatus(401);
    }

    public function test_index_lists_only_own_summaries_paginated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Summary::factory()->count(3)->for($user)->create();
        Summary::factory()->count(2)->for($other)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/summaries')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        Summary::factory()->count(2)->completed()->for($user)->create();
        Summary::factory()->failed()->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/summaries?status=failed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'failed');
    }

    public function test_show_returns_own_summary(): void
    {
        $user = User::factory()->create();
        $summary = Summary::factory()->completed()->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/summaries/{$summary->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $summary->id);
    }

    public function test_cannot_access_another_users_summary_404(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $summary = Summary::factory()->for($other)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/summaries/{$summary->id}")->assertStatus(404);
        $this->deleteJson("/api/summaries/{$summary->id}")->assertStatus(404);
    }

    public function test_delete_removes_own_summary(): void
    {
        $user = User::factory()->create();
        $summary = Summary::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/summaries/{$summary->id}")->assertStatus(204);
        $this->assertDatabaseMissing('summaries', ['id' => $summary->id]);
    }
}

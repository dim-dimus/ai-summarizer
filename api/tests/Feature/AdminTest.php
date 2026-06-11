<?php

namespace Tests\Feature;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/summaries')->assertStatus(403);
        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/stats')->assertStatus(403);
    }

    public function test_admin_lists_all_summaries_across_users(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Summary::factory()->count(2)->for($userA)->create();
        Summary::factory()->for($userB)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/summaries')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_lists_users_as_bare_array(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([['id', 'name', 'email', 'role', 'created_at']]);
    }

    public function test_admin_stats_aggregate_tokens_and_cost(): void
    {
        $admin = User::factory()->admin()->create();
        Summary::factory()->count(2)->completed()->for($admin)->create([
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'cost_usd' => 0.0015,
        ]);
        Summary::factory()->failed()->for($admin)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertStatus(200)
            ->assertJsonPath('total_summaries', 3)
            ->assertJsonPath('total_input_tokens', 2000)
            ->assertJsonPath('total_output_tokens', 400)
            ->assertJsonPath('total_cost_usd', 0.003)
            ->assertJsonPath('by_status.completed', 2)
            ->assertJsonPath('by_status.failed', 1);
    }
}

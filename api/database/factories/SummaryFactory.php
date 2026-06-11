<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Enums\SummaryStatus;
use App\Enums\SummaryStyle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Summary>
 */
class SummaryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_type' => SourceType::Text,
            'source_url' => null,
            'original_text' => fake()->paragraphs(3, true),
            'title' => fake()->sentence(),
            'style' => fake()->randomElement(SummaryStyle::cases()),
            'status' => SummaryStatus::Pending,
        ];
    }

    public function url(): static
    {
        return $this->state(fn () => [
            'source_type' => SourceType::Url,
            'source_url' => fake()->url(),
            'original_text' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SummaryStatus::Completed,
            'result_text' => fake()->paragraph(),
            'model' => 'claude-haiku-4-5-20251001',
            'input_tokens' => fake()->numberBetween(500, 5000),
            'output_tokens' => fake()->numberBetween(100, 400),
            'cost_usd' => fake()->randomFloat(6, 0.0005, 0.02),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => SummaryStatus::Failed,
            'error_message' => 'Content extraction failed.',
        ]);
    }
}

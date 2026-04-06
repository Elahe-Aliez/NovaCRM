<?php

namespace Database\Factories;

use App\Enums\ClosingResult;
use App\Enums\PipelineStage;
use App\Models\Client;
use App\Models\PipelineActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineActivity>
 */
class PipelineActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PipelineActivity>
     */
    protected $model = PipelineActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fromStage = fake()->randomElement(PipelineStage::cases())->value;
        $toStage = fake()->randomElement(PipelineStage::cases())->value;

        return [
            'client_id' => Client::factory(),
            'actor_user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'stage_changed', 'closing_result_updated']),
            'occurred_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'from_closing_result' => fake()->optional()->randomElement(ClosingResult::cases())?->value,
            'to_closing_result' => fake()->optional()->randomElement(ClosingResult::cases())?->value,
            'comment' => fake()->optional()->sentence(),
        ];
    }
}

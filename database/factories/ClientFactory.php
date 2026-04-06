<?php

namespace Database\Factories;

use App\Enums\PipelineStage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Client>
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'created_by_id' => User::factory(),
            'business_name' => fake()->company(),
            'address' => fake()->address(),
            'pipeline_stage' => fake()->randomElement(PipelineStage::cases())->value,
            'closing_result' => null,
        ];
    }
}

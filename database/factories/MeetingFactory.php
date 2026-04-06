<?php

namespace Database\Factories;

use App\Enums\MeetingResult;
use App\Enums\MeetingType;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Meeting>
     */
    protected $model = Meeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'contact_id' => null,
            'user_id' => User::factory(),
            'created_by_id' => User::factory(),
            'scheduled_at' => fake()->dateTimeBetween('-1 week', '+1 month'),
            'meeting_type' => fake()->randomElement(MeetingType::cases())->value,
            'unavailable_minutes' => fake()->numberBetween(15, 180),
            'purpose' => fake()->randomElement(['presentation', 'follow-up', 'negotiation', 'closing']),
            'result' => fake()->randomElement(MeetingResult::cases())->value,
            'comments' => fake()->optional()->sentence(),
        ];
    }
}

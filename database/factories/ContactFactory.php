<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Contact>
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'created_by_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
            'position' => fake()->jobTitle(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}

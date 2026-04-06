<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'manager@example.com',
        ], [
            'name' => 'CRM Manager',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'manager_id' => null,
            'email_verified_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }
}

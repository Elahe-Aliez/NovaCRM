<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\PipelineActivity;
use Illuminate\Database\Seeder;

class PipelineActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = Client::query()->first();

        if ($client === null) {
            return;
        }

        PipelineActivity::factory()
            ->count(2)
            ->for($client)
            ->create();
    }
}

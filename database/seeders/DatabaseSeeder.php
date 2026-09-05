<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.enabled')) {
            $this->command?->warn(
                'Demo data seeding is disabled. Set DEMO_DATA_ENABLED=true to enable it.'
            );

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}

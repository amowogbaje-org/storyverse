<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategoryGenreSeeder::class,
            SubscriptionPlanSeeder::class,
            BadgeSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}

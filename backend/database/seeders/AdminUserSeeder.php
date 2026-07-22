<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@storyverse.local'],
            [
                'name' => 'Storyverse Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'country_code' => 'US',
                'currency' => 'USD',
                'email_verified_at' => now(),
            ]
        );
    }
}

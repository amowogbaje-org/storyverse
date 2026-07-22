<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryGenreSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Romance', 'Fantasy', 'Sci-Fi', 'Thriller', 'Drama', 'Horror', 'Comedy', 'Teen Fiction'] as $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        foreach ([
            'Werewolf', 'Vampire', 'CEO', 'Billionaire', 'Mafia', 'Enemies to Lovers',
            'Second Chance', 'Sci-Fi Romance', 'Urban Fantasy', 'Mystery', 'LGBTQ+',
            'Historical', 'Supernatural', 'High School', 'Action', 'Adventure',
        ] as $name) {
            Genre::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}

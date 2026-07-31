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
        // Categories: the broad shelf a story sits on (mostly one or two per
        // story). Genres below are more specific, cross-cutting tags - a
        // Romance category story might also be tagged Werewolf + Enemies to
        // Lovers, for example. That split is deliberate: don't add the same
        // concept to both lists.
        foreach ([
            'Romance', 'Fantasy', 'Science Fiction', 'Thriller & Suspense', 'Drama',
            'Horror', 'Comedy & Satire', 'Teen Fiction (YA)', 'Mystery & Crime',
            'Adventure', 'Historical Fiction', 'Paranormal', 'Slice of Life',
            'Poetry', 'Fan Fiction', 'Short Stories', 'Non-Fiction',
        ] as $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        foreach ([
            'Werewolf', 'Vampire', 'CEO', 'Billionaire', 'Mafia', 'Enemies to Lovers',
            'Second Chance', 'Sci-Fi Romance', 'Urban Fantasy', 'Mystery', 'LGBTQ+',
            'Historical', 'Supernatural', 'High School', 'Action', 'Adventure',
            'Space Opera', 'Time Travel', 'Post-Apocalyptic', 'Cyberpunk', 'Dystopian',
            'Fairy Tale Retelling', 'Royalty', 'Reverse Harem', 'Forbidden Love',
            'Friends to Lovers', 'Arranged Marriage', 'Revenge', 'Redemption',
            'Coming of Age', 'Found Family', 'Slow Burn', 'Love Triangle', 'Zombie',
            'Magic & Witches', 'Superhero', 'Angels & Demons', 'Sports', 'Military',
            'Political Intrigue', 'Psychological', 'Detective Noir',
        ] as $name) {
            Genre::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Matches the free-text genre tokens from an uploaded manuscript (e.g.
 * "Suspense-Thriller", "Political Suspense-Thriller") against Storyverse's
 * actual curated Category list first, then the Genre tag list, since a
 * manuscript's loose "Genre:" line is really describing both concepts at
 * once and this app splits them into two separate tables (see
 * CategoryGenreSeeder's comment on that split).
 *
 * Exact matches (case/punctuation-insensitive) win outright. Anything else
 * falls back to word-overlap scoring so near-misses like "Suspense-Thriller"
 * still find "Thriller & Suspense" without needing a hardcoded synonym list
 * for every possible phrasing an author might type. A token that matches
 * nothing well enough is reported back as unmatched rather than guessed at -
 * see StoryImportController, which turns those into brand new Genre tags
 * only once the author confirms the import, never silently during preview.
 */
class GenreCategoryMatcher
{
    private const MIN_SCORE = 0.3;

    /**
     * @param  string[]  $tokens
     * @param  Collection  $categories  All Category models.
     * @param  Collection  $genres  All Genre models.
     * @return array{category_ids: int[], genre_ids: int[], unmatched: string[]}
     */
    public static function match(array $tokens, Collection $categories, Collection $genres): array
    {
        $categoryIds = [];
        $genreIds = [];
        $unmatched = [];

        foreach ($tokens as $token) {
            $normToken = self::normalize($token);
            if ($normToken === '') {
                continue;
            }

            $exactCategory = $categories->first(fn ($c) => self::normalize($c->name) === $normToken);
            if ($exactCategory) {
                $categoryIds[] = $exactCategory->id;

                continue;
            }

            $exactGenre = $genres->first(fn ($g) => self::normalize($g->name) === $normToken);
            if ($exactGenre) {
                $genreIds[] = $exactGenre->id;

                continue;
            }

            $tokenWords = self::words($token);
            $best = ['type' => null, 'id' => null, 'score' => 0.0];

            foreach ($categories as $category) {
                $score = self::similarity($tokenWords, self::words($category->name));
                if ($score > $best['score']) {
                    $best = ['type' => 'category', 'id' => $category->id, 'score' => $score];
                }
            }

            foreach ($genres as $genre) {
                $score = self::similarity($tokenWords, self::words($genre->name));
                if ($score > $best['score']) {
                    $best = ['type' => 'genre', 'id' => $genre->id, 'score' => $score];
                }
            }

            if ($best['score'] >= self::MIN_SCORE) {
                if ($best['type'] === 'category') {
                    $categoryIds[] = $best['id'];
                } else {
                    $genreIds[] = $best['id'];
                }
            } else {
                $unmatched[] = trim($token);
            }
        }

        return [
            'category_ids' => array_values(array_unique($categoryIds)),
            'genre_ids' => array_values(array_unique($genreIds)),
            'unmatched' => $unmatched,
        ];
    }

    private static function normalize(string $s): string
    {
        $s = strtolower($s);
        $s = str_replace('&', ' and ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private static function words(string $s): array
    {
        return array_values(array_filter(explode(' ', self::normalize($s))));
    }

    private static function similarity(array $a, array $b): float
    {
        if (! $a || ! $b) {
            return 0.0;
        }

        $intersect = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union ? $intersect / $union : 0.0;
    }
}

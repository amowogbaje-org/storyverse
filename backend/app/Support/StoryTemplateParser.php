<?php

namespace App\Support;

/**
 * Parses an author-uploaded Markdown manuscript into a story + episode
 * structure ready for review before anything is written to the database.
 *
 * Deliberately tolerant rather than strict about anything that isn't load-
 * bearing for the actual import (front-matter section names, season/arc
 * labeling style, em-dash vs colon after "Episode N") - real manuscripts
 * authors already have vary on exactly those things. It only hard-fails on
 * the handful of problems that would make the import itself impossible or
 * ambiguous: no title, no episodes, an episode with no body, or two
 * episodes claiming the same number.
 *
 * See StoryImportController::template() for the canonical layout this is
 * documented against - but this parser accepts real manuscripts that drift
 * from it (missing Genre/Description lines, extra front-matter sections
 * like a character-reference block, "Season"/"Arc"/"Part" wording, "Episode
 * N: Title" or "Episode N (em dash) Title") as long as the episode
 * headings themselves are recognizable.
 */
class StoryTemplateParser
{
    // Matches "### Episode 1: Title", "### Episode 1 - Title", and
    // "### Episode 1 (em dash) Title" - the three separator styles seen
    // across real author manuscripts.
    private const EPISODE_HEADING = "/^#{2,4}[ \\t]*Episode[ \\t]+(\\d+)[ \\t]*[:\u{2014}-][ \\t]*(.+?)[ \\t]*\$/mu";
    private const GENRE_LINE = '/\*\*Genre:?\*\*[ \t]*(.+)/i';
    private const DESCRIPTION_LINE = '/\*\*Description:?\*\*[ \t]*\n+(.+?)(?:\n[ \t]*---|\n#{1,6}[ \t]|\z)/is';
    private const TITLE_LINE = '/^#[ \t]+(.+?)[ \t]*$/mu';
    // Trailing markers like "**END OF PART 3 - TO BE CONTINUED**" close out
    // a manuscript, not the last episode's story content - stripped from
    // whichever episode body they land in rather than assumed to only ever
    // be last.
    private const CLOSING_MARKER = '/^\*\*END OF.*\*\*[ \t]*$/mi';

    public function parse(string $markdown): array
    {
        $errors = [];
        $warnings = [];

        $text = str_replace(["\r\n", "\r"], "\n", $markdown);

        $title = null;
        if (preg_match(self::TITLE_LINE, $text, $m)) {
            $title = trim($m[1]);
        } else {
            $errors[] = 'No title found - expected a single top-level heading like "# YOUR STORY TITLE" near the top of the file.';
        }

        // Everything before the first episode heading is "front matter":
        // title, genre, description, and anything else (character lists,
        // canon notes) that isn't part of any specific episode and is safe
        // to just ignore beyond scanning it for Genre:/Description:.
        $episodeMatches = [];
        preg_match_all(self::EPISODE_HEADING, $text, $episodeMatches, PREG_OFFSET_CAPTURE);

        $frontMatter = isset($episodeMatches[0][0])
            ? substr($text, 0, $episodeMatches[0][0][1])
            : $text;

        $genreTokens = [];
        if (preg_match(self::GENRE_LINE, $frontMatter, $m)) {
            $genreTokens = collect(preg_split('/\//', $m[1]))
                ->map(fn ($t) => trim($t, " \t*"))
                ->filter()
                ->values()
                ->all();
        }
        if (empty($genreTokens)) {
            $warnings[] = 'No "**Genre:**" line found - you will need to pick categories/genres manually before importing.';
        }

        $description = null;
        if (preg_match(self::DESCRIPTION_LINE, $frontMatter, $m)) {
            $description = trim(preg_replace('/\s+/', ' ', $m[1]));
        } else {
            $warnings[] = 'No "**Description:**" section found - a placeholder description will be used until you edit it.';
        }

        $episodes = [];
        $seenNumbers = [];
        $count = count($episodeMatches[0]);

        for ($i = 0; $i < $count; $i++) {
            $number = (int) $episodeMatches[1][$i][0];
            $epTitle = trim($episodeMatches[2][$i][0]);
            $bodyStart = $episodeMatches[0][$i][1] + strlen($episodeMatches[0][$i][0]);
            $bodyEnd = $i + 1 < $count ? $episodeMatches[0][$i + 1][1] : strlen($text);
            $body = substr($text, $bodyStart, $bodyEnd - $bodyStart);

            $body = preg_replace(self::CLOSING_MARKER, '', $body);
            // Strip the "---" divider(s) between episodes from both ends,
            // then any resulting blank edge lines.
            $body = trim(preg_replace('/^[ \t]*-{3,}[ \t]*$/m', '', $body));

            if ($epTitle === '') {
                $errors[] = "Episode {$number} is missing a title.";
            }

            if ($body === '') {
                $errors[] = "Episode {$number}".($epTitle !== '' ? " (\"{$epTitle}\")" : '').' has no content.';
            }

            if (isset($seenNumbers[$number])) {
                $errors[] = "Episode {$number} appears more than once - each episode needs a unique number.";
            }
            $seenNumbers[$number] = true;

            $episodes[] = [
                'number' => $number,
                'title' => $epTitle,
                'content' => $body,
                'word_count' => str_word_count(strip_tags($body)),
            ];
        }

        if (empty($episodes)) {
            $errors[] = 'No episodes found - expected headings like "### Episode 1: Title" (or "Episode 1 with an em dash before Title") with the episode text underneath.';
        } else {
            // File order, not declared number, decides import order - a
            // typo'd or out-of-sequence number in the source file shouldn't
            // silently reorder episodes. The numbers themselves are only
            // used above to catch duplicates; the actual database
            // episode_number is always assigned fresh on import (continuing
            // after whatever the target story already has), so gaps or a
            // non-1 starting number here are worth flagging but never block
            // the import.
            $numbers = array_column($episodes, 'number');
            $sorted = $numbers;
            sort($sorted);
            $expected = range(min($numbers), min($numbers) + count($numbers) - 1);
            if ($sorted !== $expected) {
                $warnings[] = 'Episode numbers in the file are not a clean, unbroken sequence - this will not block the import (episodes are numbered fresh on import, in file order), but double-check nothing was meant to be there and got left out.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'title' => $title,
            'description' => $description,
            'genre_tokens' => $genreTokens,
            'episodes' => $episodes,
            'episode_count' => count($episodes),
            'total_word_count' => array_sum(array_column($episodes, 'word_count')),
        ];
    }
}

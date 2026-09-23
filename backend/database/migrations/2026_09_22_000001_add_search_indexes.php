<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SearchController::native() runs `title ilike '%q%'` / `description ilike
 * '%q%'` / `display_name ilike '%q%'` - a leading wildcard, which a normal
 * B-tree index (what ->unique()/->index() create) can't use at all. Without
 * this, every search keystroke is a full sequential scan of `stories` (and
 * `pen_names`), and that scan gets linearly slower as the catalog grows -
 * the opposite of the O(log n) lookup an index is supposed to give you.
 *
 * pg_trgm's GIN indexes are the fix: they index substrings, so
 * `column ilike '%anything%'` can use one regardless of where the match
 * falls in the string - something a B-tree fundamentally cannot do. Safe to
 * run against an existing large table (this only builds a new index, no
 * data rewritten), though on a very large `stories` table you may want to
 * run this migration during low traffic - building a GIN index does hold a
 * brief lock at the end of the build.
 *
 * Postgres-only. On MySQL (see config/database.php's connection for the
 * cPanel/shared-hosting path this app also supports) there's no trigram
 * index equivalent that a plain LIKE '%q%' query can use - MySQL only
 * accelerates substring search via a FULLTEXT index + MATCH()...AGAINST(),
 * which is a different query shape (whole-word matching, not substring;
 * SearchController would need to branch its query, not just gain an index).
 * That's exactly what SearchController::native()'s own comment already
 * earmarks laravel/scout + Meilisearch for - the real fix for MySQL
 * deployments is wiring that up, not a partial FULLTEXT index the current
 * query can't even use. This migration no-ops there rather than pretending
 * to help or hard-failing the whole migration run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Requires superuser or a role with CREATE privilege on
            // extensions, which the app's migration user should already
            // have during setup (same requirement most managed Postgres
            // hosts, incl. RDS/Supabase, grant by default). If this fails,
            // ask your DB host to enable pg_trgm once and re-run this
            // migration.
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            DB::statement('CREATE INDEX IF NOT EXISTS stories_title_trgm_idx ON stories USING GIN (title gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS stories_description_trgm_idx ON stories USING GIN (description gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS pen_names_display_name_trgm_idx ON pen_names USING GIN (display_name gin_trgm_ops)');
        }

        // genre_story's primary key is (story_id, genre_id), which only
        // serves lookups that filter by story_id (or both columns) - neither
        // Postgres nor MySQL will use a composite index to efficiently
        // filter on the second column alone. The /genres endpoint's
        // whereHas('stories') filters by genre_id first, so it's been doing
        // a full scan of this join table on every call. Driver-agnostic, so
        // this part runs either way.
        if (Schema::hasTable('genre_story')) {
            Schema::table('genre_story', function ($table) {
                $table->index('genre_id', 'genre_story_genre_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS stories_title_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS stories_description_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS pen_names_display_name_trgm_idx');
        }

        if (Schema::hasTable('genre_story')) {
            Schema::table('genre_story', function ($table) {
                $table->dropIndex('genre_story_genre_id_idx');
            });
        }
    }
};

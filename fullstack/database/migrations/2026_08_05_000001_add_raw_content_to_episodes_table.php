<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // The author's original, unstyled text - never overwritten by the
            // styling agent. `content` remains what readers actually see (and
            // starts out equal to this on save); the agent later replaces
            // `content` with a styled version while this column stays
            // untouched, so nothing the author wrote is ever lost even if a
            // future re-styling pass goes wrong.
            $table->longText('raw_content')->nullable()->after('content');

            // Set only when raw_content itself changes (an author edit), never
            // when the agent writes to `content` - lets CraftProfessorExportController
            // tell "the author changed this" apart from "the agent just
            // finished styling this" without re-sending unchanged text.
            $table->timestamp('raw_content_updated_at')->nullable()->after('raw_content');

            // Null = queued for the styling agent (StyleEpisodes picks these up,
            // 10 per run). Set the moment the agent successfully styles this
            // episode; an author edit resets it back to null to queue a re-style.
            $table->timestamp('styled_at')->nullable()->after('raw_content_updated_at');

            // Consecutive styling failures for this episode. StyleEpisodes gives
            // up and falls back to showing the raw text after a few failed
            // attempts, rather than burning API calls on a permanently-broken
            // episode every 15 minutes forever.
            $table->unsignedTinyInteger('styling_attempts')->default(0)->after('styled_at');

            $table->index('styled_at');
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn(['raw_content', 'raw_content_updated_at', 'styled_at', 'styling_attempts']);
        });
    }
};

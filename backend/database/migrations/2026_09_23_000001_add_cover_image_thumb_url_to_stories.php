<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable, not backfilled by this migration - see the
 * stories:backfill-cover-thumbnails console command for filling it in for
 * stories that already have a cover_image_url from before this existed.
 * StoryCardPresenter::card() falls back to the full cover_image_url when
 * this is null, so nothing breaks for a story in between "migrated" and
 * "backfilled" - it just doesn't get the smaller-payload win until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('cover_image_thumb_url')->nullable()->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('cover_image_thumb_url');
        });
    }
};

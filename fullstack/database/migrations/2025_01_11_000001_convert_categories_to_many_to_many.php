<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_story', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['story_id', 'category_id']);
        });

        // Every existing story had exactly one category_id - carry that over as
        // its first category before the column disappears, so nothing loses its
        // existing categorization in this migration.
        DB::table('stories')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->select('id', 'category_id')
            ->chunkById(500, function ($rows) {
                DB::table('category_story')->insertOrIgnore(
                    $rows->map(fn ($row) => [
                        'story_id' => $row->id,
                        'category_id' => $row->category_id,
                    ])->all()
                );
            });

        Schema::table('stories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained();
        });

        // Best-effort restore: each story gets whichever category happened to
        // be first, since a many-to-many can't losslessly collapse back into
        // a single column if a story picked up more than one along the way.
        DB::table('category_story')
            ->orderBy('story_id')
            ->select('story_id', 'category_id')
            ->get()
            ->groupBy('story_id')
            ->each(function ($rows, $storyId) {
                DB::table('stories')->where('id', $storyId)->update(['category_id' => $rows->first()->category_id]);
            });

        Schema::dropIfExists('category_story');
    }
};

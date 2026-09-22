<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // Null = never sent to CraftProfessor. Set whenever this episode's
            // content is included in a /api/stories/{slug}/json response - see
            // CraftProfessorExportController for why this exists (bounding how
            // much full episode text goes out per request, without ever
            // permanently dropping an episode from the export).
            $table->timestamp('content_synced_at')->nullable()->after('word_count');
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn('content_synced_at');
        });
    }
};

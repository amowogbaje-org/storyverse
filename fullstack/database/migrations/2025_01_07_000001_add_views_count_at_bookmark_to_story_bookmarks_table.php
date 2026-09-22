<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_bookmarks', function (Blueprint $table) {
            $table->unsignedInteger('views_count_at_bookmark')->default(0)->after('story_id');
        });
    }

    public function down(): void
    {
        Schema::table('story_bookmarks', function (Blueprint $table) {
            $table->dropColumn('views_count_at_bookmark');
        });
    }
};

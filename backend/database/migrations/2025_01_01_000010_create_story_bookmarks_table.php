<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("story_bookmarks", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->foreignId("story_id")->constrained()->cascadeOnDelete();
            $table->timestamp("created_at")->useCurrent();

            $table->unique(["user_id", "story_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("story_bookmarks");
    }
};

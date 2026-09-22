<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("episodes", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("story_id")->constrained()->cascadeOnDelete();
            $table->string("title");
            $table->unsignedInteger("episode_number");
            $table->longText("content");
            $table->unsignedInteger("word_count")->default(0);
            $table->enum("status", ["draft", "published"])->default("draft");
            $table->timestamp("published_at")->nullable();
            $table->timestamps();

            $table->unique(["story_id", "episode_number"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("episodes");
    }
};

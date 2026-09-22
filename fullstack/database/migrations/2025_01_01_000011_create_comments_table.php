<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("comments", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->foreignId("story_id")->constrained()->cascadeOnDelete();
            $table->foreignId("episode_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("parent_id")->nullable()->constrained("comments")->cascadeOnDelete();
            $table->text("body");
            $table->timestamps();
            $table->softDeletes();

            $table->index(["story_id", "episode_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("comments");
    }
};

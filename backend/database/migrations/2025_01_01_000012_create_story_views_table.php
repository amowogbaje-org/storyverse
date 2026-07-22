<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("story_views", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("story_id")->constrained()->cascadeOnDelete();
            $table->string("session_hash", 64);
            $table->timestamp("viewed_at")->useCurrent();

            $table->index(["story_id", "session_hash"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("story_views");
    }
};

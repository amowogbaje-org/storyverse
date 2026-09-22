<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("stories", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("pen_name_id")->constrained()->cascadeOnDelete();
            $table->foreignId("category_id")->constrained();
            $table->string("title");
            $table->string("slug")->unique();
            $table->text("description");
            $table->string("cover_image_url");
            $table->enum("status", ["draft", "published", "archived"])->default("draft");
            $table->enum("access_type", ["free", "premium"])->default("free");
            $table->boolean("is_completed")->default(false);
            $table->unsignedInteger("episodes_count")->default(0);
            $table->unsignedBigInteger("views_count")->default(0);
            $table->unsignedInteger("likes_count")->default(0);
            $table->unsignedInteger("bookmarks_count")->default(0);
            $table->unsignedInteger("comments_count")->default(0);
            $table->timestamp("published_at")->nullable();
            $table->timestamps();

            $table->index(["status", "published_at"]);
            $table->index(["status", "views_count"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("stories");
    }
};

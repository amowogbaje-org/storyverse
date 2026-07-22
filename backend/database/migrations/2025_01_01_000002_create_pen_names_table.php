<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("pen_names", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->string("display_name");
            $table->string("slug")->unique();
            $table->text("bio")->nullable();
            $table->string("avatar_url")->nullable();
            $table->boolean("is_default")->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("pen_names");
    }
};

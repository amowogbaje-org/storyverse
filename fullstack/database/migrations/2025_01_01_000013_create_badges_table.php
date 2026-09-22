<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("badges", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string("name");
            $table->string("slug")->unique();
            $table->string("description");
            $table->string("icon_url")->nullable();
            $table->enum("category", ["reading", "social", "spending", "streak", "discovery"]);
            $table->enum("tier", ["bronze", "silver", "gold", "platinum"]);
            $table->string("criteria_type");
            $table->unsignedInteger("criteria_value");
            $table->enum("reward_type", ["none", "bonus_access", "recommendation"])->default("none");
            $table->json("reward_payload")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("badges");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("users", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("email")->unique();
            $table->string("password")->nullable();
            $table->string("phone")->nullable();
            $table->string("country_code", 2)->nullable();
            $table->string("currency", 3)->nullable();
            $table->string("avatar_url")->nullable();
            $table->enum("role", ["reader", "author", "admin"])->default("reader");
            $table->unsignedTinyInteger("current_streak_days")->default(0);
            $table->date("last_streak_date")->nullable();
            $table->timestamp("email_verified_at")->nullable();
            $table->timestamp("last_active_at")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("users");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("user_activity_events", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->string("event_type");
            $table->json("metadata")->nullable();
            $table->timestamp("created_at")->useCurrent();

            $table->index(["user_id", "event_type"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("user_activity_events");
    }
};

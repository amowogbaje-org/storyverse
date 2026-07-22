<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("subscription_plans", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("country_code", 2);
            $table->string("currency", 3);
            $table->decimal("price", 10, 2);
            $table->enum("billing_interval", ["month", "year"])->default("month");
            $table->boolean("is_active")->default(true);
            $table->timestamps();

            $table->index(["country_code", "is_active"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("subscription_plans");
    }
};

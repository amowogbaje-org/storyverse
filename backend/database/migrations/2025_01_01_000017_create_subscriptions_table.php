<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("subscriptions", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->foreignId("plan_id")->constrained("subscription_plans");
            $table->enum("gateway", ["flutterwave", "paystack", "stripe"]);
            $table->string("gateway_subscription_id")->nullable();
            $table->decimal("locked_price", 10, 2);
            $table->string("locked_currency", 3);
            $table->enum("status", ["active", "past_due", "cancelled", "expired"])->default("active");
            $table->timestamp("current_period_end");
            $table->timestamp("cancelled_at")->nullable();
            $table->timestamps();

            $table->index(["user_id", "status"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("subscriptions");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("payments", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->foreignId("subscription_id")->nullable()->constrained()->nullOnDelete();
            $table->enum("gateway", ["flutterwave", "paystack", "stripe"]);
            $table->string("gateway_reference")->unique();
            $table->decimal("amount", 10, 2);
            $table->string("currency", 3);
            $table->enum("status", ["pending", "success", "failed", "refunded"])->default("pending");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("payments");
    }
};

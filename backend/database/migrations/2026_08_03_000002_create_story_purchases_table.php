<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_purchases', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('gateway_reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();

            // A reader can only own one successful purchase of a given story -
            // repeat "buy" clicks before the first completes are fine (they just
            // create another pending row/checkout attempt), but two *successful*
            // purchases of the same story by the same user should never happen.
            $table->index(['user_id', 'story_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_purchases');
    }
};

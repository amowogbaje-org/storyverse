<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('pen_name_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the tipper
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('gateway');
            $table->string('gateway_reference')->unique();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('message', 500)->nullable(); // optional note from the tipper to the author
            $table->timestamps();

            $table->index(['pen_name_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};

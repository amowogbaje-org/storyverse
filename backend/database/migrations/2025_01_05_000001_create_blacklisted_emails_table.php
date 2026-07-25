<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("blacklisted_emails", function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string("email")->unique();
            $table->string("reason")->nullable();
            $table->foreignId("blacklisted_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("blacklisted_emails");
    }
};

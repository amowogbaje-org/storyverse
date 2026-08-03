<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Null = not for sale as a direct purchase (access is still governed
            // by access_type/subscription as before). An author sets this to
            // enable a "Buy this book" option that unlocks every episode
            // regardless of subscription status - see StoryAccessService.
            $table->decimal('purchase_price', 10, 2)->nullable()->after('access_type');
            $table->string('purchase_currency', 3)->nullable()->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'purchase_currency']);
        });
    }
};

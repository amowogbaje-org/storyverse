<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_prices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            // One price per currency per story - saving a new price for a
            // currency that's already set is an update, not a second row.
            $table->unique(['story_id', 'currency']);
        });

        // Every story that had the old single price/currency set becomes a
        // one-row entry here, so nothing an author already priced is lost by
        // this migration - it just now lives in a table that allows more
        // than one currency per story instead of exactly one.
        DB::table('stories')
            ->whereNotNull('purchase_price')
            ->whereNotNull('purchase_currency')
            ->select('id', 'purchase_price', 'purchase_currency')
            ->orderBy('id')
            ->each(function ($story) {
                DB::table('story_prices')->insert([
                    'story_id' => $story->id,
                    'currency' => $story->purchase_currency,
                    'amount' => $story->purchase_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'purchase_currency']);
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable()->after('access_type');
            $table->string('purchase_currency', 3)->nullable()->after('purchase_price');
        });

        // Restore from whichever single price each story had before - if a
        // story ended up with more than one currency after this migration
        // ran, USD wins as the most likely "original" one; anything beyond
        // that can't be losslessly folded back into one column and is
        // intentionally dropped on rollback.
        DB::table('story_prices')
            ->select('story_id', 'currency', 'amount')
            ->orderByRaw("currency = 'USD' desc")
            ->get()
            ->unique('story_id')
            ->each(function ($price) {
                DB::table('stories')->where('id', $price->story_id)->update([
                    'purchase_price' => $price->amount,
                    'purchase_currency' => $price->currency,
                ]);
            });

        Schema::dropIfExists('story_prices');
    }
};

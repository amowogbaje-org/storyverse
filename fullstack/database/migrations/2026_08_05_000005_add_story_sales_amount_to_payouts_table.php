<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            // Direct attribution now that a purchase is for one specific
            // story: this author's share of story_prices sales for their own
            // stories in the period, not a platform-wide proportional
            // estimate (that's what subscription_share_amount was, back when
            // subscription revenue couldn't be tied to any one story - see
            // GenerateMonthlyPayouts). subscription_share_amount is kept,
            // not renamed, so past payouts still read correctly; it will
            // simply always compute to 0 for any period after subscriptions
            // were removed, which is the accurate number, not a bug.
            $table->decimal('story_sales_amount', 10, 2)->default(0)->after('subscription_share_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('story_sales_amount');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Temporary premium access granted as a reward (badge unlocks,
            // referral milestones - see BonusAccessService), independent of
            // any purchase. Null or in the past = no bonus access active.
            // Replaces extending/creating a Subscription row for this, which
            // stopped making sense once subscriptions were removed as the
            // paid-access mechanism (stories are bought individually now -
            // see story_prices).
            $table->timestamp('premium_access_until')->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('premium_access_until');
        });
    }
};

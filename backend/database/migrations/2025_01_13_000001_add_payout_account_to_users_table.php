<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('payout_account_name')->nullable()->after('referral_reward_tier_claimed');
            $table->string('payout_account_number')->nullable()->after('payout_account_name');
            $table->string('payout_bank_name')->nullable()->after('payout_account_number');
            // Flutterwave's transfer API identifies banks by a numeric code, not
            // just a free-text name - needed if/when automated transfers are
            // switched on (see config/payouts.php). Manual payouts can leave it
            // blank and just use the bank name above.
            $table->string('payout_bank_code')->nullable()->after('payout_bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payout_account_name', 'payout_account_number', 'payout_bank_name', 'payout_bank_code']);
        });
    }
};

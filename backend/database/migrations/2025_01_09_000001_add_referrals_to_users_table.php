<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('role');
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
            // How many multiples of 10 verified referrals this user has already
            // been rewarded for - see ReferralService::onReferredUserVerified().
            // Without this, hitting 11, 12, 13... verified referrals would keep
            // re-granting the tier-1 reward on every single new activity event.
            $table->unsignedInteger('referral_reward_tier_claimed')->default(0)->after('referred_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn(['referral_code', 'referral_reward_tier_claimed']);
        });
    }
};

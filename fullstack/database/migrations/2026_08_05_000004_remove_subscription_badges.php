<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Matches BadgeSeeder's slugs for the badges removed from that seeder in
    // this same change - see the "Spending / Subscription" category there.
    // First Unlock, Big Spender, and Wide Reader stay: they're keyed off
    // spend/purchases, which still make sense in a purchase-only world.
    private array $removedSlugs = [
        'supporter',
        'loyal-patron',
        'devoted-patron',
        'patrons-circle',
        'annual-vip',
    ];

    public function up(): void
    {
        // user_badges has cascadeOnDelete on badge_id, so this also removes
        // anyone's already-earned copies of these badges - there's no
        // meaningful "keep the badge but stop offering it" middle ground
        // here, since its whole criteria no longer resolves to anything.
        DB::table('badges')->whereIn('slug', $this->removedSlugs)->delete();
    }

    public function down(): void
    {
        // Deliberately not reseeded here - BadgeSeeder no longer defines
        // these, and who had actually earned one isn't recoverable from this
        // migration alone. Re-run BadgeSeeder's old definitions manually if
        // this ever needs to be undone.
    }
};

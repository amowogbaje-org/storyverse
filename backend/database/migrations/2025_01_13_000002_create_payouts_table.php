<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('subscription_share_amount', 10, 2)->default(0);
            $table->decimal('tips_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3);
            // pending: generated, awaiting action. processing: transfer submitted to
            // gateway, awaiting confirmation. paid: money sent (manually or via
            // gateway). failed: an attempted automated transfer was rejected.
            $table->string('status')->default('pending');
            // Snapshot of the account details at the time of the payout, not a live
            // reference to the user's current settings - if they change their bank
            // details later, past payout records should still show where that
            // specific payment actually went.
            $table->string('payout_account_name')->nullable();
            $table->string('payout_account_number')->nullable();
            $table->string('payout_bank_name')->nullable();
            $table->string('payout_bank_code')->nullable();
            $table->string('gateway_transfer_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'period_end', 'currency']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate from user_activity_events on purpose: that table's user_id is a
 * NOT NULL foreign key, so it can only ever record something *after* a User
 * row exists. Most of the drop-off we actually care about (bad password,
 * blacklisted email, an already-registered-but-unverified email, a Google
 * token that failed verification) happens before that, so it needs its own
 * table with a nullable user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_attempts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            // 'native' = email/password via /auth/register, 'google' = Google
            // Identity Services via /auth/google, 'otp' = passwordless
            // email/phone signup completed via /auth/otp/verify.
            $table->string('channel');

            // Email or phone number as attempted - kept even when it never
            // resolves to a user, since that's exactly what a drop-off
            // report needs to look up.
            $table->string('identifier')->nullable();

            // 'succeeded' | 'failed' | 'pending' (pending = account exists
            // but is still waiting on OTP verification, e.g. a resend).
            $table->string('status');

            // Short machine-readable code for why, e.g. 'validation_failed',
            // 'email_blacklisted', 'email_taken_verified',
            // 'unverified_resend', 'invalid_google_token', 'invalid_otp',
            // 'otp_sent', 'new_account', 'existing_account_linked'.
            $table->string('reason')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['channel', 'status']);
            $table->index('identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_attempts');
    }
};

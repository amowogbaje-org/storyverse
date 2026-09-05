<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Only the SHA-256 hash is ever stored - the plaintext token is
            // handed to the client once at issue time and never persisted,
            // the same principle as password hashing: a leaked database row
            // alone should never be enough to impersonate a reader.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            // Set on logout, on rotation (each refresh consumes the token
            // that redeemed it), or if a token is ever force-revoked (e.g.
            // password change / "sign out everywhere"). Null = still usable.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};

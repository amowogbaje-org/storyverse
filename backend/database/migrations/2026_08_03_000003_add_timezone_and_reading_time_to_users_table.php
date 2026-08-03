<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // IANA identifier, e.g. "Africa/Lagos" - the frontend should capture
            // this via Intl.DateTimeFormat().resolvedOptions().timeZone at
            // signup/first login rather than asking the user to pick one.
            // Falls back to UTC anywhere it's null (see reminder commands).
            $table->string('timezone')->nullable()->after('country_code');

            // Local time-of-day, e.g. "20:00:00", that the reader wants to be
            // nudged to read. Null = no preference set; reminder commands fall
            // back to a sensible default evening slot in the user's timezone.
            $table->time('preferred_reading_time')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'preferred_reading_time']);
        });
    }
};

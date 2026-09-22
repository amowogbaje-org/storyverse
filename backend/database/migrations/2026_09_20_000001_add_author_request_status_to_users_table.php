<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'none' - never asked. 'pending' - asked, waiting on an admin.
            // 'rejected' - an admin said no (they can ask again later, which
            // moves this back to 'pending'). Granting doesn't need a state
            // here - role just becomes 'author' and this resets to 'none'.
            $table->enum('author_request_status', ['none', 'pending', 'rejected'])
                ->default('none')->after('role');
            $table->timestamp('author_requested_at')->nullable()->after('author_request_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['author_request_status', 'author_requested_at']);
        });
    }
};

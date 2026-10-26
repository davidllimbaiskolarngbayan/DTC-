<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('claim_notification_recipients') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('claim_notification_recipients')
            ->whereNotIn('user_id', DB::table('users')->select('id')->where('role', 'staff'))
            ->delete();
    }

    public function down(): void
    {
        // Admin recipients are not restored; alerts should only target staff accounts.
    }
};

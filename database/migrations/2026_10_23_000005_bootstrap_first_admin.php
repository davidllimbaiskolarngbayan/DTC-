<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        if (! DB::table('users')->where('role', 'admin')->exists()) {
            DB::table('users')->orderBy('id')->limit(1)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        // Keep the bootstrap administrator to avoid locking operators out on rollback.
    }
};

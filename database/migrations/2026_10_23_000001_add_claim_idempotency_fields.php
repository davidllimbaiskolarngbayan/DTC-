<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'idempotency_key')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->uuid('idempotency_key')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('claims', 'idempotency_payload_hash')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->char('idempotency_payload_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('claims', 'idempotency_key') ? 'idempotency_key' : null,
            Schema::hasColumn('claims', 'idempotency_payload_hash') ? 'idempotency_payload_hash' : null,
        ]);

        if ($columns) {
            Schema::table('claims', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

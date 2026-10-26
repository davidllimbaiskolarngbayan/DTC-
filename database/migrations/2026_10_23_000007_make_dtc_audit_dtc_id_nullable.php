<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dtc_audit_logs') && Schema::hasColumn('dtc_audit_logs', 'dtc_id')) {
            Schema::table('dtc_audit_logs', function (Blueprint $table) {
                $table->unsignedInteger('dtc_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep dtc_id nullable: system-level audit events do not belong to a registration.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'incident_date')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->date('incident_date')->nullable();
            });
        }

        if (! Schema::hasColumn('claims', 'affected_tyres')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->string('affected_tyres', 160)->nullable();
            });
        }

        if (! Schema::hasColumn('claim_histories', 'customer_message')) {
            Schema::table('claim_histories', function (Blueprint $table) {
                $table->text('customer_message')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('claim_histories', 'customer_message')) {
            Schema::table('claim_histories', fn (Blueprint $table) => $table->dropColumn('customer_message'));
        }

        $columns = array_filter([
            Schema::hasColumn('claims', 'incident_date') ? 'incident_date' : null,
            Schema::hasColumn('claims', 'affected_tyres') ? 'affected_tyres' : null,
        ]);
        if ($columns) {
            Schema::table('claims', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};